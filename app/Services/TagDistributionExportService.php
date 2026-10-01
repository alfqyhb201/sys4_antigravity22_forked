<?php

namespace App\Services;

use App\Models\ClientDesigner;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

class TagDistributionExportService
{
    /**
     * تصدير بيانات التوزيع الأسبوعي إلى ملف إكسل متعدد الشيتات (شيت لكل مصمم).
     *
     * @param  Collection|array<int, ClientDesigner>  $assignments
     */
    public function export(Collection|array $assignments, string $selectedWeek): string
    {
        $assignmentsCollection = collect($assignments);

        $options = new Options;
        $options->DEFAULT_COLUMN_WIDTH = 22;

        $tempFile = tempnam(sys_get_temp_dir(), 'tag_dist_').'.xlsx';

        $writer = new Writer($options);
        $writer->openToFile($tempFile);

        $border = new Border(
            new BorderPart(Border::BOTTOM, Color::rgb(210, 215, 225), Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::TOP, Color::rgb(210, 215, 225), Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::RIGHT, Color::rgb(210, 215, 225), Border::WIDTH_THIN, Border::STYLE_SOLID),
            new BorderPart(Border::LEFT, Color::rgb(210, 215, 225), Border::WIDTH_THIN, Border::STYLE_SOLID)
        );

        $titleStyle = (new Style)
            ->setFontBold()
            ->setFontSize(13)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(24, 43, 73)) // Deep Navy #182b49
            ->setCellAlignment(CellAlignment::CENTER);

        $headerStyle = (new Style)
            ->setFontBold()
            ->setFontSize(11)
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(37, 99, 235)) // Royal Blue #2563eb
            ->setCellAlignment(CellAlignment::CENTER)
            ->setBorder($border);

        $clientNameStyle = (new Style)
            ->setFontBold()
            ->setFontSize(10)
            ->setShouldWrapText(true)
            ->setCellAlignment(CellAlignment::RIGHT)
            ->setBorder($border);

        $designsCountStyle = (new Style)
            ->setFontBold()
            ->setFontSize(10)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setBorder($border);

        $dataStyle = (new Style)
            ->setFontSize(10)
            ->setShouldWrapText(true)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setBorder($border);

        $totalStyle = (new Style)
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor(Color::rgb(15, 23, 42))
            ->setBackgroundColor(Color::rgb(241, 245, 249)) // Slate 100
            ->setCellAlignment(CellAlignment::CENTER)
            ->setBorder($border);

        $totalClientStyle = (new Style)
            ->setFontBold()
            ->setFontSize(10)
            ->setFontColor(Color::rgb(15, 23, 42))
            ->setBackgroundColor(Color::rgb(241, 245, 249))
            ->setCellAlignment(CellAlignment::RIGHT)
            ->setBorder($border);

        // حساب أيام الأسبوع (من السبت إلى الخميس - 6 أيام عمل)
        $weekStart = Carbon::parse($selectedWeek);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            if ($day->dayOfWeek !== Carbon::FRIDAY) {
                $days[] = $day;
            }
        }

        $weekRangeLabel = sprintf(
            'الأسبوع من %s إلى %s',
            $weekStart->translatedFormat('j F Y'),
            $weekStart->copy()->addDays(6)->translatedFormat('j F Y')
        );

        $groupedAssignments = $assignmentsCollection->groupBy(function ($assignment) {
            return $assignment->designer?->user?->name ?: 'مصمم غير محدد';
        });

        if ($groupedAssignments->isEmpty()) {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('توزيع التاقات');

            $headers = ['اسم العميل', 'عدد التصاميم'];
            foreach ($days as $day) {
                $headers[] = $day->translatedFormat('l').' ('.$day->format('d/m').')';
            }
            $writer->addRow(Row::fromValues($headers, $headerStyle));
            $writer->addRow(Row::fromValues(['لا توجد بيانات توزيع لهذا الأسبوع'], $dataStyle));

            $writer->close();

            return $tempFile;
        }

        $usedSheetNames = [];
        $isFirstSheet = true;

        foreach ($groupedAssignments as $designerName => $designerAssignments) {
            $safeSheetName = $this->sanitizeSheetName((string) $designerName, $usedSheetNames);

            if ($isFirstSheet) {
                $sheet = $writer->getCurrentSheet();
                $sheet->setName($safeSheetName);
                $isFirstSheet = false;
            } else {
                $sheet = $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName($safeSheetName);
            }

            // ترويسة الأعمدة (السطر الأول في الشيت)
            $headers = ['اسم العميل', 'عدد التصاميم'];
            foreach ($days as $day) {
                $headers[] = $day->translatedFormat('l').' ('.$day->format('d/m').')';
            }
            $writer->addRow(Row::fromValues($headers, $headerStyle));

            // تجهيز تجميع التوزيعات للعملاء حسب التاريخ
            $totalDesigns = 0;
            $dayTagCounts = array_fill_keys(array_map(fn ($d) => $d->format('Y-m-d'), $days), 0);

            foreach ($designerAssignments as $assignment) {
                $clientName = $assignment->client?->company ?: ($assignment->client?->client_name ?: "عميل #{$assignment->client_id}");

                $weeklyDesigns = (int) ($assignment->contract?->weekly_designs_count ?? $assignment->client?->currentContract?->weekly_designs_count ?? 0);
                $totalDesigns += $weeklyDesigns;

                $distributionsByDate = ($assignment->distributions ?? collect())->groupBy(function ($dist) {
                    if ($dist->distribution_date instanceof \Carbon\CarbonInterface) {
                        return $dist->distribution_date->format('Y-m-d');
                    }

                    return Carbon::parse($dist->distribution_date)->format('Y-m-d');
                });

                $rowCells = [
                    Cell::fromValue($clientName, $clientNameStyle),
                    Cell::fromValue($weeklyDesigns, $designsCountStyle),
                ];

                foreach ($days as $day) {
                    $dateKey = $day->format('Y-m-d');
                    $dayDists = $distributionsByDate->get($dateKey, collect());

                    if ($dayDists->isNotEmpty()) {
                        $dayTagCounts[$dateKey] += $dayDists->count();

                        $tagTexts = [];
                        foreach ($dayDists as $dist) {
                            $tagName = $dist->tag?->name ?? 'تاق';
                            if (! empty($dist->custom_idea)) {
                                $tagTexts[] = "• {$tagName}\n  ({$dist->custom_idea})";
                            } elseif ($dist->idea?->name) {
                                $tagTexts[] = "• {$tagName}\n  ({$dist->idea->name})";
                            } else {
                                $tagTexts[] = "• {$tagName}";
                            }
                        }

                        $cellContent = implode("\n", $tagTexts);
                        $rowCells[] = Cell::fromValue($cellContent, $dataStyle);
                    } else {
                        $rowCells[] = Cell::fromValue('-', $dataStyle);
                    }
                }

                $writer->addRow(new Row($rowCells));
            }

            // سطر الإجماليات
            $totalCells = [
                Cell::fromValue("الإجمالي ({$designerAssignments->count()} عملاء)", $totalClientStyle),
                Cell::fromValue($totalDesigns, $totalStyle),
            ];

            foreach ($days as $day) {
                $dateKey = $day->format('Y-m-d');
                $count = $dayTagCounts[$dateKey] ?? 0;
                $totalCells[] = Cell::fromValue("{$count} تاق", $totalStyle);
            }

            $writer->addRow(new Row($totalCells));
        }

        $writer->close();

        return $tempFile;
    }

    /**
     * توليد اسم ملف منظم واحترافي.
     */
    public function generateFileName(string $selectedWeek): string
    {
        $weekDate = Carbon::parse($selectedWeek)->format('Y-m-d');

        return "توزيع_التاقات_{$weekDate}.xlsx";
    }

    /**
     * تنظيف اسم الشيت ليتوافق مع قيود إكسل (31 حرف كحد أقصى وبدون رموز خاصة).
     *
     * @param  array<int, string>  $usedNames
     */
    protected function sanitizeSheetName(string $name, array &$usedNames): string
    {
        $clean = preg_replace('/[\\\\\\/\?\*\:\[\]]/u', '', trim($name));
        if (empty($clean)) {
            $clean = 'مصمم';
        }

        $clean = mb_substr($clean, 0, 31);
        $finalName = $clean;
        $counter = 2;

        while (in_array($finalName, $usedNames, true)) {
            $suffix = " ({$counter})";
            $maxLen = 31 - mb_strlen($suffix);
            $finalName = mb_substr($clean, 0, $maxLen).$suffix;
            $counter++;
        }

        $usedNames[] = $finalName;

        return $finalName;
    }
}

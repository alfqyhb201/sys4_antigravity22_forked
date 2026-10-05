<?php

namespace App\Providers\Filament;

use App\Filament\Auth\CustomLogin;
use App\Filament\Pages\AccountingDashboard;
use App\Filament\Pages\ActiveSessionsPage;
use App\Filament\Pages\ActivityLogPage;
use App\Filament\Pages\Archive\ArchivedClients;
use App\Filament\Pages\CreateOrder;
use App\Filament\Pages\CurrencySettingsPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DesignerDashboard;
use App\Filament\Pages\DesignerDistribution;
use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Pages\MediaManagerPage;
use App\Filament\Pages\ReviewerDashboard;
use App\Filament\Pages\RolePermissionManager;
use App\Filament\Pages\SocialMediaPublishing;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\ClientNeedResource;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ClientSocialMediaResource;
use App\Filament\Resources\ClientTemplateResource;
use App\Filament\Resources\ComplaintResource;
use App\Filament\Resources\ContractResource;
use App\Filament\Resources\CurrencyResource;
use App\Filament\Resources\CustodyResource;
use App\Filament\Resources\DesignerResource;
use App\Filament\Resources\DesignTaskResource;
use App\Filament\Resources\IdeaResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\LocationResource;
use App\Filament\Resources\ReceiptResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\SocialMediaResource;
use App\Filament\Resources\TagGroupResource;
use App\Filament\Resources\TagResource;
use App\Filament\Resources\UsersResource;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(CustomLogin::class)
            ->profile(isSimple: false)
            ->databaseNotifications()
            ->databaseNotificationsPolling(null)
            ->colors([
                // 'primary' => Color::Violet,
                'primary' => '#441188',
                'secondary' => '#ff6600',
                'warning' => '#ff6600',
            ])
            ->brandLogo(asset('images/true-nav.png'))
            ->brandLogoHeight('2.5rem')
            ->brandName('TrueERP')
            ->favicon(asset('images/true-nav.png'))
            ->darkMode(true)
            ->defaultThemeMode(ThemeMode::Dark)
            ->font('Cairo')
            ->homeUrl(fn (): string => auth()->user()?->getDefaultDashboardUrl() ?? '/admin')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                \App\Filament\Widgets\WeeklyDistributionAlertWidget::class,
                \App\Filament\Widgets\AdminStatsWidget::class,
                \App\Filament\Widgets\UnresolvedComplaintsWidget::class,
                \App\Filament\Widgets\RecentActivityWidget::class,
                \App\Filament\Widgets\UserCountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\UpdateUserLastActivity::class,
            ])
            ->broadcasting(true)
            // ->unsavedChangesAlerts()
            ->sidebarCollapsibleOnDesktop()
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => Blade::render('
                    @can("view_any_design_task")
                        <a href="{{ \App\Filament\Resources\DesignTaskResource::getUrl() }}"
                           class="relative inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5"
                           title="المهام الجانبية">
                            <x-heroicon-o-clipboard-document-list class="w-5 h-5" />
                             @php
                                 $currentUser = auth()->user();
                                 $pendingQuery = \App\Models\DesignTask::where("status", "in_review");
                                 if ($currentUser && ! $currentUser->hasRole(["admin", "super_admin"])) {
                                     $pendingQuery->where("assigner_id", $currentUser->id);
                                 }
                                 $pendingCount = $pendingQuery->count();
                             @endphp
                            @if($pendingCount > 0)
                                <span class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">{{ $pendingCount }}</span>
                            @endif
                        </a>
                    @endcan
                '),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render("    
                    <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        if (!('Notification' in window)) return;

                        const userId = @js(auth()->id());
                        if (!userId) return;

                        const seenKey = 'shown_desktop_notifications_' + userId;

                        function getShownIds() {
                            try {
                                return JSON.parse(localStorage.getItem(seenKey) || '[]');
                            } catch (e) {
                                return [];
                            }
                        }

                        function rememberShown(id) {
                            let shown = getShownIds();
                            if (!shown.includes(id)) {
                                shown.push(id);
                                if (shown.length > 200) shown = shown.slice(-200);
                                localStorage.setItem(seenKey, JSON.stringify(shown));
                            }
                        }

                        document.addEventListener('click', function request() {
                            if (Notification.permission === 'default') {
                                Notification.requestPermission();
                            }
                        }, { once: true });

                        function isImportExportNotification(title) {
                            const keywords = ['اكتمل الاستيراد', 'أكتمل التصدير', 'import completed', 'export completed'];
                            return keywords.some(function (keyword) {
                                return (title || '').toLowerCase().indexOf(keyword.toLowerCase()) !== -1;
                            });
                        }

                        function handleNotification(data) {
                            const notificationId = data?.id;
                            if (!notificationId) return;

                            const shown = getShownIds();
                            if (shown.includes(notificationId)) return;

                            const title = data.title || 'إشعار جديد 🔔';
                            if (isImportExportNotification(title)) return;

                            if (Notification.permission === 'granted') {
                                rememberShown(notificationId);

                                const body = data.body || data.message || '';
                                const icon = data.icon || '/images/true-nav.png';
                                const link = data.url || (data.actions && data.actions[0] ? data.actions[0].url : null) || '/admin';

                                try {
                                    const notif = new Notification(title, {
                                        body: body,
                                        icon: icon,
                                        tag: notificationId,
                                    });

                                    notif.onclick = function () {
                                        window.focus();
                                        window.location.href = link;
                                    };
                                } catch (err) {
                                    console.error('[Desktop Notification Error]', err);
                                }
                            } else if (Notification.permission === 'default') {
                                Notification.requestPermission().then(function (perm) {
                                    if (perm === 'granted') {
                                        handleNotification(data);
                                    }
                                });
                            }
                        }

                        function checkUnreadNotifications() {
                            fetch('/admin/notifications/unread-count')
                                .then(r => r.ok ? r.json() : Promise.reject())
                                .then(data => {
                                    if (!data.notifications || !data.notifications.length) return;
                                    data.notifications.forEach(handleNotification);
                                })
                                .catch(() => {});
                        }

                        function startEchoListener() {
                            if (!window.Echo) return;

                            const channelName = 'App.Models.User.' + userId;
                            window.Echo.private(channelName)
                                .listen('.database-notifications.sent', function () {
                                    checkUnreadNotifications();
                                });
                        }

                        if (window.Echo) {
                            startEchoListener();
                        } else {
                            window.addEventListener('EchoLoaded', startEchoListener, { once: true });
                        }
                    });
                    </script>
                "),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('@include("filament.hooks.file-upload-paste")'),
            )
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                $user = auth()->user();

                // ──────────────────────────────────────────────
                // Helper: role check
                // ──────────────────────────────────────────────
                $hasRole = fn (string $role): bool => $user?->hasRole($role) ?? false;
                $can = fn (string $perm): bool => $user?->can($perm) ?? false;

                // ──────────────────────────────────────────────
                // Top-level items (no group)
                // ──────────────────────────────────────────────
                $topItems = [];

                if ($hasRole('admin') || $hasRole('super_admin') || $can('view_admin_dashboard')) {
                    $topItems[] = NavigationItem::make('الرئيسية')
                        ->icon('heroicon-o-home')
                        ->url(Dashboard::getUrl())
                        ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.dashboard'));
                }

                if ($hasRole('designer') || $can('view_designer_dashboard')) {
                    $topItems[] = NavigationItem::make('لوحة المصمم')
                        ->icon('heroicon-o-paint-brush')
                        ->url(DesignerDashboard::getUrl())
                        ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.designer-dashboard'));
                }

                if ($hasRole('reviewer') || $can('view_reviewer_dashboard')) {
                    $topItems[] = NavigationItem::make('لوحة المراجع')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->url(ReviewerDashboard::getUrl())
                        ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.reviewer-dashboard'));
                }

                if ($hasRole('supervisor') || $can('view_supervisor_dashboard')) {
                    $topItems[] = NavigationItem::make('واجهة المشرف')
                        ->icon('heroicon-o-presentation-chart-line')
                        ->url(\App\Filament\Pages\SupervisorDashboard::getUrl())
                        ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.supervisor-dashboard'));
                }

                if ($can('view_financial_reports')) {
                    $topItems[] = NavigationItem::make('لوحة التحكم المالية')
                        ->icon('heroicon-o-presentation-chart-line')
                        ->url(AccountingDashboard::getUrl())
                        ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.accounting-dashboard'));
                }

                if ($hasRole('supervisor') || $can('view_supervisor_dashboard') || $can('view_sending_follow_up')) {
                    $topItems[] = NavigationItem::make('واجهة الإرسال')
                        ->icon('heroicon-o-paper-airplane')
                        ->url(\App\Filament\Pages\SendingFollowUp::getUrl())
                        ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.sending-follow-up'));
                }

                if ($hasRole('social_media') || $hasRole('admin') || $can('view_social_media_publishing')) {
                    $topItems[] = NavigationItem::make('واجهة السوشيال ميديا')
                        ->icon('heroicon-o-megaphone')
                        ->url(SocialMediaPublishing::getUrl())
                        ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.social-media-publishing'));
                }

                // ──────────────────────────────────────────────
                // 1. 💰 المالية  (first — per user request)
                // ──────────────────────────────────────────────
                $canSeeFinancial = $can('view_any_contract')
                    || $can('view_any_invoice')
                    || $can('view_any_receipt');

                $groups = [];

                if ($canSeeFinancial) {
                    $financialItems = [];
                    if ($can('view_any_contract')) {
                        $financialItems[] = ContractResource::getNavigationItems();
                    }
                    if ($can('view_any_invoice')) {
                        $financialItems[] = InvoiceResource::getNavigationItems();
                    }
                    if ($can('view_any_receipt')) {
                        $financialItems[] = ReceiptResource::getNavigationItems();
                    }

                    $groups[] = NavigationGroup::make('المالية')
                        ->icon('heroicon-o-currency-dollar')
                        ->items(array_merge(...$financialItems));
                }

                // ──────────────────────────────────────────────
                // 2. 👥 CRM
                // ──────────────────────────────────────────────
                $crmItems = [];
                if ($can('view_any_client')) {
                    $crmItems[] = ClientResource::getNavigationItems();
                }
                if ($can('view_any_client_social_media') || $hasRole('admin') || $hasRole('social_media')) {
                    $crmItems[] = ClientSocialMediaResource::getNavigationItems();
                }
                if ($can('view_any_social_media')) {
                    $crmItems[] = SocialMediaResource::getNavigationItems();
                }
                if ($can('view_any_location')) {
                    $crmItems[] = LocationResource::getNavigationItems();
                }
                if ($can('view_any_client_template')) {
                    $crmItems[] = ClientTemplateResource::getNavigationItems();
                }
                if ($can('view_any_complaint')) {
                    $crmItems[] = ComplaintResource::getNavigationItems();
                }

                if (! empty($crmItems)) {
                    $groups[] = NavigationGroup::make('CRM')
                        ->icon('heroicon-o-briefcase')
                        ->items(array_merge(...$crmItems));
                }

                // ──────────────────────────────────────────────
                // 3. 📋 التخطيط
                // ──────────────────────────────────────────────
                $planningItems = [];
                if ($can('view_designer_distribution')) {
                    $planningItems[] = DesignerDistribution::getNavigationItems();
                }
                if ($can('view_tag_distribution')) {
                    $planningItems[] = \App\Filament\Pages\TagDistribution::getNavigationItems();
                }
                if ($can('view_create_order')) {
                    $planningItems[] = CreateOrder::getNavigationItems();
                }

                if (! empty($planningItems)) {
                    $groups[] = NavigationGroup::make('التخطيط')
                        ->icon('heroicon-o-arrows-right-left')
                        ->items(array_merge(...$planningItems));
                }

                // ──────────────────────────────────────────────
                // 4. 📝 المحتوى
                // ──────────────────────────────────────────────
                $contentItems = [];
                if ($can('view_any_tag_group')) {
                    $contentItems[] = TagGroupResource::getNavigationItems();
                }
                if ($can('view_any_category')) {
                    $contentItems[] = CategoryResource::getNavigationItems();
                }
                if ($can('view_any_client_need')) {
                    $contentItems[] = ClientNeedResource::getNavigationItems();
                }
                if ($can('view_any_tag')) {
                    $contentItems[] = TagResource::getNavigationItems();
                }
                if ($can('view_any_idea')) {
                    $contentItems[] = IdeaResource::getNavigationItems();
                }

                if (! empty($contentItems)) {
                    $groups[] = NavigationGroup::make('المحتوى')
                        ->icon('heroicon-o-squares-2x2')
                        ->items(array_merge(...$contentItems));
                }

                // ──────────────────────────────────────────────
                // 5. 👤 المستخدمون
                // ──────────────────────────────────────────────
                $usersItems = [];
                if ($can('view_any_users')) {
                    $usersItems[] = UsersResource::getNavigationItems();
                }
                if ($can('view_any_designer')) {
                    $usersItems[] = DesignerResource::getNavigationItems();
                }
                if ($can('view_any_role')) {
                    $usersItems[] = RoleResource::getNavigationItems();
                }
                if ($hasRole('admin') || $hasRole('super_admin') || $can('view_any_role')) {
                    $usersItems[] = RolePermissionManager::getNavigationItems();
                }
                if ($can('view_any_custody')) {
                    $usersItems[] = CustodyResource::getNavigationItems();
                }

                if (! empty($usersItems)) {
                    $groups[] = NavigationGroup::make('المستخدمون')
                        ->icon('heroicon-o-users')
                        ->items(array_merge(...$usersItems));
                }

                // ──────────────────────────────────────────────
                // 6. 📦 إدارة المهام
                // ──────────────────────────────────────────────
                if ($can('view_any_design_task')) {
                    $groups[] = NavigationGroup::make('إدارة المهام')
                        ->icon('heroicon-o-clipboard-document-list')
                        ->items(DesignTaskResource::getNavigationItems());
                }

                // ──────────────────────────────────────────────
                // 7. ⚙️ الإعدادات
                // ──────────────────────────────────────────────
                if ($can('view_any_currency') || $can('manage_settings') || $can('view_activity_log') || $can('view_active_sessions') || $can('view_media_manager') || $hasRole('super_admin') || $hasRole('admin')) {
                    $settingsItems = [];
                    if ($can('view_any_currency')) {
                        $settingsItems[] = CurrencyResource::getNavigationItems();
                    }
                    if ($can('manage_settings') || $hasRole('super_admin') || $hasRole('admin')) {
                        $settingsItems[] = GeneralSettingsPage::getNavigationItems();
                        $settingsItems[] = CurrencySettingsPage::getNavigationItems();
                    }
                    if ($can('view_activity_log') || $hasRole('super_admin') || $hasRole('admin')) {
                        $settingsItems[] = ActivityLogPage::getNavigationItems();
                    }
                    if ($can('view_active_sessions') || $hasRole('super_admin') || $hasRole('admin')) {
                        $settingsItems[] = ActiveSessionsPage::getNavigationItems();
                    }
                    if ($can('view_media_manager') || $hasRole('super_admin') || $hasRole('admin')) {
                        $settingsItems[] = MediaManagerPage::getNavigationItems();
                    }

                    if (! empty($settingsItems)) {
                        $groups[] = NavigationGroup::make('الإعدادات')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->items(array_merge(...$settingsItems));
                    }
                }

                // ──────────────────────────────────────────────
                // 8. 🗄️ الأرشيف
                // ──────────────────────────────────────────────
                if ($can('view_archive')) {
                    $groups[] = NavigationGroup::make('الأرشيف')
                        ->icon('heroicon-o-archive-box')
                        ->items(ArchivedClients::getNavigationItems());
                }

                // ──────────────────────────────────────────────
                // Build final navigation
                // ──────────────────────────────────────────────
                return $builder
                    ->items($topItems)
                    ->groups($groups);
            })
            ->viteTheme('resources/css/app.css');
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\PermissionDiscoveryService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * صفحة إدارة وتخصيص صلاحيات الأدوار (الواجهة المطورة والتجريبية).
 *
 * تتيح استكشاف الصلاحيات من كود Filament تلقائياً بدون Seeder،
 * ومزامنتها، وتخصيصها للأدوار، وفحص الصلاحيات عبر محاكي فحص مباشر.
 */
class RolePermissionManager extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'filament.pages.role-permission-manager';

    protected static ?string $navigationGroup = 'المستخدمون';

    protected static ?string $navigationLabel = 'إدارة الصلاحيات (تجريبي)';

    protected static ?string $title = 'إدارة وتخصيص صلاحيات الأدوار (مطور)';

    protected static ?int $navigationSort = 10;

    /**
     * الدور المحدد حالياً للتعديل.
     */
    public ?int $selectedRoleId = null;

    /**
     * مصفوفة أسماء الصلاحيات المحددة للدور الحالي.
     *
     * @var array<string>
     */
    public array $selectedPermissions = [];

    /**
     * نص البحث لتصفية الصلاحيات.
     */
    public string $searchQuery = '';

    /**
     * التبويب النشط (resources, pages, custom, tester).
     */
    public string $activeTab = 'resources';

    /**
     * حقل اسم الدور الجديد عند الإنشاء.
     */
    public string $newRoleName = '';

    /**
     * حالة إظهار مودال إنشاء دور جديد.
     */
    public bool $showNewRoleModal = false;

    /**
     * حالة إظهار مودال فحص وتجربة الصلاحيات.
     */
    public bool $showTesterModal = false;

    /**
     * نوع الفحص (role أو user).
     */
    public string $testSubjectType = 'role';

    /**
     * معرف الدور أو المستخدم المراد اختباره.
     */
    public ?int $testSubjectId = null;

    /**
     * اسم الصلاحية المراد اختبارها.
     */
    public string $testPermission = '';

    /**
     * نتيجة اختبار الصلاحية.
     *
     * @var array{hasAccess: bool, reason: string}|null
     */
    public ?array $testResult = null;

    /**
     * التحقق من صلاحية الوصول للصفحة.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && (
            $user->hasRole(['admin', 'super_admin']) ||
            $user->can('manage_settings')
        );
    }

    /**
     * إعداد الصفحة عند التحميل.
     */
    public function mount(PermissionDiscoveryService $discoveryService): void
    {
        abort_unless(static::canAccess(), 403, 'غير مصرح لك بالوصول لإدارة صلاحيات النظام.');

        // مزامنة الصلاحيات لضمان وجودها
        $discoveryService->syncMissingPermissions();

        $firstRole = Role::orderBy('name')->first();
        if ($firstRole) {
            $this->selectedRoleId = $firstRole->id;
            $this->loadRolePermissions();
        }
    }

    /**
     * جلب الأدوار الحالية مع عدّاد الصلاحيات.
     */
    public function getRolesProperty(): Collection
    {
        return Role::withCount('permissions')->orderBy('name')->get();
    }

    /**
     * جلب المستخدمين للمحاكي.
     */
    public function getUsersProperty(): Collection
    {
        return User::select('id', 'name', 'email')->orderBy('name')->limit(50)->get();
    }

    /**
     * جلب بيانات الاستكشاف لكافة الموارد والصفحات والإجراءات.
     */
    public function getDiscoveredDataProperty(PermissionDiscoveryService $discoveryService): array
    {
        $resources = $discoveryService->discoverResources();
        $pages = $discoveryService->discoverPages();
        $custom = $discoveryService->discoverCustomActions();

        // تطبيق فلتر البحث إن وجد
        if (! empty($this->searchQuery)) {
            $query = mb_strtolower(trim($this->searchQuery));

            // تصفية النماذج
            $resources = array_filter($resources, function ($res) use ($query) {
                if (str_contains(mb_strtolower($res['label']), $query) || str_contains(mb_strtolower($res['entity']), $query)) {
                    return true;
                }
                foreach ($res['permissions'] as $p) {
                    if (str_contains(mb_strtolower($p['name']), $query) || str_contains(mb_strtolower($p['label']), $query)) {
                        return true;
                    }
                }

                return false;
            });

            // تصفية الصفحات
            $pages = array_filter($pages, function ($p) use ($query) {
                return str_contains(mb_strtolower($p['label']), $query)
                    || str_contains(mb_strtolower($p['permission']), $query)
                    || str_contains(mb_strtolower($p['name']), $query);
            });

            // تصفية الصلاحيات الخاصة
            $custom = array_filter($custom, function ($c, $key) use ($query) {
                return str_contains(mb_strtolower($c['label']), $query)
                    || str_contains(mb_strtolower($c['description']), $query)
                    || str_contains(mb_strtolower($key), $query);
            }, ARRAY_FILTER_USE_BOTH);
        }

        return [
            'resources' => $resources,
            'pages' => $pages,
            'custom' => $custom,
        ];
    }

    /**
     * تجميع النماذج حسب المجموعات.
     */
    public function getGroupedResourcesProperty(): array
    {
        $data = $this->discoveredData;
        $grouped = [];

        foreach ($data['resources'] as $entityKey => $resource) {
            $group = $resource['group'];
            $grouped[$group][$entityKey] = $resource;
        }

        return $grouped;
    }

    /**
     * اختيار دور مختلف للتعديل.
     */
    public function selectRole(int $roleId): void
    {
        $this->selectedRoleId = $roleId;
        $this->loadRolePermissions();
    }

    /**
     * تحميل صلاحيات الدور الحالي المختار.
     */
    public function loadRolePermissions(): void
    {
        if (! $this->selectedRoleId) {
            $this->selectedPermissions = [];

            return;
        }

        $role = Role::find($this->selectedRoleId);
        $this->selectedPermissions = $role ? $role->permissions()->pluck('name')->toArray() : [];
    }

    /**
     * تفعيل أو إلغاء صلاحية واحدة.
     */
    public function togglePermission(string $permName): void
    {
        if (in_array($permName, $this->selectedPermissions)) {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, [$permName]));
        } else {
            $this->selectedPermissions[] = $permName;
        }
    }

    /**
     * تحديد أو إلغاء كافة صلاحيات CRUD لنموذج معين.
     */
    public function toggleResourceAll(string $entityKey, PermissionDiscoveryService $discoveryService): void
    {
        $resources = $discoveryService->discoverResources();
        if (! isset($resources[$entityKey])) {
            return;
        }

        $crudPerms = array_column($resources[$entityKey]['permissions'], 'name');
        $allSelected = empty(array_diff($crudPerms, $this->selectedPermissions));

        if ($allSelected) {
            // إلغاء تحديد الكل لهذا النموذج
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $crudPerms));
        } else {
            // تحديد الكل لهذا النموذج
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $crudPerms)));
        }
    }

    /**
     * تحديد أو إلغاء تحديد مجموعة كاملة من النماذج.
     */
    public function toggleGroupAll(string $groupName, PermissionDiscoveryService $discoveryService): void
    {
        $resources = $discoveryService->discoverResources();
        $groupPerms = [];

        foreach ($resources as $res) {
            if ($res['group'] === $groupName) {
                foreach ($res['permissions'] as $p) {
                    $groupPerms[] = $p['name'];
                }
            }
        }

        $allSelected = ! empty($groupPerms) && empty(array_diff($groupPerms, $this->selectedPermissions));

        if ($allSelected) {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $groupPerms));
        } else {
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $groupPerms)));
        }
    }

    /**
     * تحديد كافة الصلاحيات المكتشفة في النظام.
     */
    public function selectAllPermissions(PermissionDiscoveryService $discoveryService): void
    {
        $all = array_keys($discoveryService->getAllDiscoveredPermissions());
        $this->selectedPermissions = $all;

        Notification::make()
            ->title('تم تحديد جميع صلاحيات النظام')
            ->info()
            ->send();
    }

    /**
     * إلغاء تحديد كافة الصلاحيات.
     */
    public function deselectAllPermissions(): void
    {
        $this->selectedPermissions = [];

        Notification::make()
            ->title('تم إلغاء تحديد كافة الصلاحيات')
            ->info()
            ->send();
    }

    /**
     * حفظ الصلاحيات المحددة للدور المختار.
     */
    public function saveRolePermissions(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! $this->selectedRoleId) {
            Notification::make()
                ->title('يرجى اختيار دور أولاً')
                ->warning()
                ->send();

            return;
        }

        $role = Role::findOrFail($this->selectedRoleId);

        // التأكد من وجود الصلاحيات في قاعدة البيانات
        foreach ($this->selectedPermissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        $role->syncPermissions($this->selectedPermissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Notification::make()
            ->title("تم حفظ صلاحيات الدور ({$role->name}) بنجاح")
            ->body('تم تعيين '.count($this->selectedPermissions).' صلاحية للدور.')
            ->success()
            ->send();
    }

    /**
     * تشغيل مزامنة الصلاحيات من كود Filament.
     */
    public function syncSystemPermissions(PermissionDiscoveryService $discoveryService): void
    {
        abort_unless(static::canAccess(), 403);

        $result = $discoveryService->syncMissingPermissions();

        if ($result['created_count'] > 0) {
            $createdList = implode(', ', array_slice($result['created_permissions'], 0, 5));
            if (count($result['created_permissions']) > 5) {
                $createdList .= ' وغيرها...';
            }

            Notification::make()
                ->title('تمت المزامنة بنجاح!')
                ->body("تم اكتشاف {$result['total_discovered']} صلاحية في النظام، وتمت إضافة {$result['created_count']} صلاحية جديدة: ({$createdList})")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('النظام محدث بالكامل!')
                ->body("تم فحص كافة الموارد والصفحات ({$result['total_discovered']} صلاحية)، وجميعها مسجلة ومطابقة لقاعدة البيانات.")
                ->info()
                ->send();
        }
    }

    /**
     * إنشاء دور جديد.
     */
    public function createNewRole(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->validate([
            'newRoleName' => 'required|string|min:2|max:50|unique:roles,name',
        ], [
            'newRoleName.required' => 'اسم الدور مطلوب.',
            'newRoleName.unique' => 'هذا الدور موجود مسبقاً.',
            'newRoleName.min' => 'يجب ألا يقل اسم الدور عن حرفين.',
        ]);

        $role = Role::create([
            'name' => trim($this->newRoleName),
            'guard_name' => 'web',
        ]);

        $this->selectedRoleId = $role->id;
        $this->newRoleName = '';
        $this->showNewRoleModal = false;
        $this->loadRolePermissions();

        Notification::make()
            ->title("تم إنشاء الدور ({$role->name}) بنجاح")
            ->body('يمكنك الآن تحديد الصلاحيات وحفظها لهذا الدور.')
            ->success()
            ->send();
    }

    /**
     * اختبار ومحاكاة صلاحية لمستخدم أو دور.
     */
    public function runPermissionTest(): void
    {
        if (empty($this->testPermission)) {
            Notification::make()
                ->title('يرجى اختيار الصلاحية المراد اختبارها')
                ->warning()
                ->send();

            return;
        }

        if ($this->testSubjectType === 'role') {
            $roleId = $this->testSubjectId ?: $this->selectedRoleId;
            $role = Role::find($roleId);

            if (! $role) {
                Notification::make()->title('يرجى اختيار دور صالح للفحص')->warning()->send();

                return;
            }

            $has = $role->hasPermissionTo($this->testPermission);
            $this->testResult = [
                'hasAccess' => $has,
                'subjectName' => "الدور: {$role->name}",
                'permission' => $this->testPermission,
                'reason' => $has
                    ? "الدور '{$role->name}' يمتلك الصلاحية '{$this->testPermission}' بشكل مباشر."
                    : "الدور '{$role->name}' لا يمتلك الصلاحية '{$this->testPermission}'.",
            ];
        } else {
            $user = User::find($this->testSubjectId);
            if (! $user) {
                Notification::make()->title('يرجى اختيار مستخدم للفحص')->warning()->send();

                return;
            }

            $has = $user->can($this->testPermission);
            $userRoles = $user->getRoleNames()->toArray();
            $rolesStr = empty($userRoles) ? 'لا يوجد أدوار' : implode(', ', $userRoles);

            $this->testResult = [
                'hasAccess' => $has,
                'subjectName' => "المستخدم: {$user->name} ({$user->email})",
                'permission' => $this->testPermission,
                'reason' => $has
                    ? "المستخدم لديه الإذن عبر الأدوار المسندة إليه: [{$rolesStr}]."
                    : "المستخدم لا يمتلك الصلاحية المطلوبة سواء عبر أدواره [{$rolesStr}] أو بشكل مباشر.",
            ];
        }
    }
}

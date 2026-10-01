# 🎯 TrueERP — دليل وكلاء AI

## 📋 نبذة عن المشروع

**TrueERP** هو نظام ERP/محاسبي متكامل مع إدارة متقدمة للمهام وتوزيع المصممين والوسوم، مبني على **Laravel 12** مع **Filament 3** و **Livewire 3**. اللغة الأساسية للواجهة هي **العربية** (RTL) مع دعم كامل للوضع الليلي والنهاري.

---

## 🚀 الأوامر الأساسية

| الأمر | الوصف |
| :--- | :--- |
| `php artisan test` | تشغيل جميع الاختبارات |
| `php artisan test --filter=testName` | تشغيل اختبار محدد أو دالة معينة |
| `php artisan test tests/Feature/XxxTest.php` | تشغيل ملف اختبار ميزة محدد |
| `vendor/bin/pint --dirty` | تنسيق الملفات المعدلة تلقائياً (Laravel Pint) |
| `vendor/bin/pint` | تنسيق كل ملفات المشروع بالكامل |
| `npm run build` | بناء الأصول للإنتاج (Vite) |
| `npm run dev` | تشغيل سيرفر Vite في وضع التطوير |
| `composer run dev` | تشغيل التطوير المشترك (npm + artisan serve) |
| `php artisan make:model ModelName -mf` | إنشاء موديل مع migration و factory |
| `php artisan make:test TestName --phpunit` | إنشاء اختبار Feature بصيغة PHPUnit |
| `php artisan make:livewire ComponentName` | إنشاء مكون Livewire جديد |
| `php artisan filament:optimize` | تحسين تخزين كاش Filament للإنتاج |
| `php artisan filament:optimize-clear` | مسح كاش Filament عند حدوث تعارض |

> **⚠️ قاعدة إلزامية:** كل تعديل أو ميزة برمجية جديدة يجب أن يرافقها اختبار آلي (Feature / Unit Test) يثبت صحة عملها، وتشغيل `vendor/bin/pint` قبل الاعتماد.

---

## 🗂️ هيكل الدليل المعماري

```
app/
├── Console/Commands/           # أوامر Artisan المجدولة والتشغيلية (تجديد، توزيع، استيراد، إصلاح)
├── Enums/                      # تعدادات PHP (TitleCase: ImportanceLevel, OrderStatus, etc.)
├── Filament/
│   ├── Auth/                   # شاشات تسجيل الدخول والمصادقة المخصصة
│   ├── Components/             # مكونات Filament UI المخصصة وتتبع المستخدم
│   ├── Pages/                  # لوحات التحكم المتخصصة وشاشات العمليات المستقلة
│   ├── Resources/              # موارد إدارة البيانات CRUD الكاملة
│   └── Widgets/                # ودجات الإحصائيات، الجداول المصغرة، والرسوم البيانية
├── Http/                       # Controllers, Form Requests, Middleware
├── Livewire/                   # مكونات Livewire التفاعلية
├── Models/                     # نماذج Eloquent والعلاقات
├── Notifications/              # إشعارات النظام والبريد
├── Observers/                  # مراقبو أحداث النماذج (Model Observers)
├── Policies/                   # سياسات الصلاحيات والتحقق من الأذونات
├── Providers/                  # Service Providers
└── Services/                   # طبقة الأعمال والخدمات المعقدة (Business Logic Services)
bootstrap/
├── app.php                     # تسجيل الـ Middleware، التوجيه، والاستثناءات
└── providers.php               # تسجيل الـ Service Providers
config/
├── designer_distribution.php   # إعدادات وخوارزميات توزيع المصممين
├── permission.php              # إعدادات حزمة الصلاحيات Spatie
└── filament.php                # إعدادات وإضافات لوحة Filament
routes/
├── web.php                     # مسارات الويب
├── console.php                 # مسارات أوامر Artisan المجدولة
└── channels.php                # قنوات البث الحي (Reverb / Echo)
tests/
├── Feature/                    # اختبارات الميزات والتكامل (Filament / Livewire / Services)
└── Unit/                       # اختبارات منطق الأعمال والوحدات الفردية
.agents/
└── rules/                      # القواعد السلوكية للوكيل (no-browser-use, planning, etc.)
```

---

## 🎨 أنماط Filament المتبعة في المشروع

1. **Resources:** بناء CRUD كامل لكل نموذج، وتحديد الصلاحيات عبر الـ Policies.
2. **Forms:** استخدام مكونات `Filament\Forms\Components` وربط العلاقات بـ `->relationship()`.
3. **Tables:** استخدام `Filament\Tables\Columns` مع تفعيل `->searchable()` و `->sortable()` للأعمدة الحيوية.
4. **Actions:** استخدام الإجراءات المدمجة مع تأكيد بمودال داخلي (`Action::make()->requiresConfirmation()`).
5. **Widgets:** بناء ودجات لوحة القيادة مع مراعاة الأداء وتفادي استعلامات N+1.
6. **Form Requests & Validation:** استخدام التحقق الدقيق والرسائل العربية الواضحة.

---

## 🔄 مجالات ووحدات النظام الرئيسية (Core Domains)

### 1. 👥 توزيع المصممين وإدارة المهام (Designer Distribution & Tasks)
* **الخدمة الرئيسية:** `app/Services/DesignerDistributionService.php`
* **لوحات التحكم:**
  * صفحة التوزيع: `app/Filament/Pages/DesignerDistribution.php`
  * لوحة المصمم: `app/Filament/Pages/DesignerDashboard.php`
  * لوحة المشرف: `app/Filament/Pages/SupervisorDashboard.php`
  * لوحة المراجع: `app/Filament/Pages/ReviewerDashboard.php`
* **الموارد:** `DesignerResource.php`، `DesignTaskResource.php`
* **الأوامر:** `AutoDistributeDesigners.php`
* **الإعدادات والتوثيق:** `config/designer_distribution.php`، `docs/DESIGNER_DISTRIBUTION_ALGORITHM.md`

### 2. 🏷️ نظام توزيع الوسوم (Tag Distribution)
* **الخدمة الرئيسية:** `app/Services/TagDistributionService.php`
* **خدمات المراجعة والتصدير:** `WeeklyDistributionAuditService.php`، `TagDistributionExportService.php`
* **الصفحة:** `app/Filament/Pages/TagDistribution.php`
* **الموارد:** `TagResource.php`، `TagGroupResource.php`
* **النماذج:** `Tag.php`، `TagGroup.php`، `ClientTagDistribution.php`

### 3. 💰 النظام المالي والاشتراكات والعملات (Finance, Contracts & Multi-Currency)
* **الاشتراكات:** `Contract.php`، `ContractResource.php`
* **الفواتير والسندات:** `Invoice.php`، `InvoiceResource.php`، `Receipt.php`، `ReceiptResource.php`
* **تخصيص السندات والدفعات:** `ReceiptAllocation.php`، `PaymentService.php`
* **العملات والصرف:** `Currency.php`، `ExchangeRate.php`، `CurrencyService.php`، `CurrencySettingsPage.php`
* **العهد والحسابات البنكية:** `Custody.php`، `CustodyResource.php`، `BankAccount.php`
* **لوحة المحاسبة والتقارير:** `AccountingDashboard.php`، `FinancialDashboardService.php`، `ClientDebtorsReportService.php`
* **الأوامر المالية:**
  * `RenewContractsAndGenerateInvoices.php` (تجديد الاشتراكات الآلي وتوليد الفواتير)
  * `RecalculateBaseAmounts.php` (إعادة احتساب المبالغ بالعملة الأساسية)
  * `RepairHistoricalAdvanceReceiptsCommand.php` (معالجة السندات المقدمة السابقة)

### 4. 🏢 إدارة العملاء والشكاوى (CRM & Complaints)
* **العملاء:** `Client.php`، `ClientResource.php`
* **الميزات:** السقف الائتماني، فترات الإيقاف، التصنيف، تقييم الأهمية (`ImportanceRatioService.php`)
* **الشكاوى:** `Complaint.php`، `ComplaintResource.php`
* **تتبع النشاط والمستخدمين:** `app/Filament/Components/UserTrackingSection.php`

### 5. 💡 الأفكار والقوالب والنشر الاجتماعي (Ideas, Templates & Publishing)
* **الأفكار والاحتياجات:** `Idea.php`، `IdeaResource.php`، `ClientNeed.php`، `ClientNeedResource.php`
* **القوالب:** `ClientTemplate.php`، `ClientTemplateResource.php`، `GenerateClientTemplateThumbnails.php`
* **وسائل التواصل والنشر:** `SocialMedia.php`، `SocialMediaPublishing.php`، `SendingFollowUp.php`

### 6. 📥 خدمات الاستيراد المخصص (Custom Data Imports)
* **الصفحات:** `CustomClientImport.php`، `CustomInvoiceImport.php`، `CustomReceiptImport.php`، `CustomCategoryImport.php`، `CustomTagImport.php`، `CustomLocationImport.php`
* **الخدمات:** `ClientImportService.php`، `InvoiceImportService.php`، `ReceiptImportService.php`، إلخ.

### 7. 🔐 الصلاحيات، الجلسات، وسجلات التدقيق (Security & Auditing)
* **إدارة الصلاحيات:** `RoleResource.php`، `RolePermissionManager.php`، `PermissionDiscoveryService.php`
* **سجل الأنشطة والجلسات:** `ActivityLogPage.php`، `ActiveSessionsPage.php` (مراقبة الجلسات الحية)

---

## 🌐 المعايير والاتفاقيات الأساسية (Conventions)

* **اللغة العربية (RTL):** الخط المعتمد هو `Cairo`، وجميع التسميات (`->label('...')`) ورسائل التنبيه يجب أن تكون باللغة العربية الواضحة.
* **الوضع الليلي (Dark Mode):** مدعوم بالكامل ومفعّل افتراضياً؛ احرص على استخدام أصناف Tailwind مع البادئة `dark:` عند بناء واجهات مخصصة.
* **FilamentUser Interface:** أي نموذج مستخدم (`User`) يدخل للوحة التحكم يجب أن يطبق `FilamentUser` للتحقق من صلاحية الوصول (`canAccessPanel`).
* **الصلاحيات:** مبنية على `spatie/laravel-permission` مع استخدام الـ Trait `HasRoles`.
* **تتبع النشاطات:** تسجيل العمليات الحساسة عبر `spatie/laravel-activitylog`.
* **البث الفوري (Broadcasting):** استخدام Laravel Reverb مع Laravel Echo للتحديثات اللحظية.
* **العلاقات والأنواع (Typing):** تحديد الـ Return Types بدقة في Eloquent (`BelongsTo`, `HasMany`, `bool`, إلخ) مع الالتزام بـ PHP 8.2+.
* **تنسيق الكود:** تشغيل `vendor/bin/pint --dirty` قبل إنهاء أي مهمة برمجية.

---

## 📚 مراجع وتوثيق إضافي

- [GEMINI.md](./GEMINI.md) — إرشادات بيئة Antigravity / Gemini و Laravel Boost
- [CLAUDE.md](./CLAUDE.md) — إرشادات أداة Claude Code
- [docs/](./docs/) — مستندات تفصيلية للفواتير والعمليات
- [DESIGN.md](./DESIGN.md) — وثيقة التصميم المعماري
- [README_DISTRIBUTION.md](./README_DISTRIBUTION.md) — توثيق خوارزمية توزيع المصممين
- [COMPLETED.md](./COMPLETED.md) — سجل الإنجازات والتحديثات المنفذة

<div x-data="{
    templates: [
        { label: '✏️ خطأ إملائي', text: 'يرجى تصحيح الخطأ الإملائي في النص المكتوب.' },
        { label: '🎨 ألوان الهوية', text: 'يرجى الالتزام بألوان الهوية البصرية للعميل.' },
        { label: '🖼️ موضع الشعار', text: 'يرجى تعديل موضع الشعار وحجمه ليكون متناسقاً وواضحاً.' },
        { label: '📐 المقاسات والأبعاد', text: 'يرجى التأكد من دقة المقاسات والأبعاد المناسبة للمنصة.' },
        { label: '📝 مطابقة الفكرة', text: 'التصميم غير مطابق لفكرة ومحتوى المنشور المطلوب.' },
        { label: '🔍 دقة وجودة الصور', text: 'يرجى تحسين جودة ودقة العناصر والصور المستخدمة في التصميم.' }
    ],
    insertTemplate(text) {
        const field = document.getElementById('reviewer-feedback-input') || document.querySelector('textarea[wire\\:model*=\'reviewer_feedback\']') || document.querySelector('textarea');
        if (field) {
            if (field.value && field.value.trim().length > 0) {
                field.value += '\n' + text;
            } else {
                field.value = text;
            }
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
}" class="mb-3">
    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">⚡ قوالب تعديل سريعة (اضغط للإضافة):</label>
    <div class="flex flex-wrap gap-1.5">
        <template x-for="t in templates" :key="t.label">
            <button type="button" @click="insertTemplate(t.text)"
                class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs font-medium text-gray-700 transition-all hover:border-red-300 hover:bg-red-50 hover:text-red-700 active:scale-95 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-red-800 dark:hover:bg-red-950/40 dark:hover:text-red-400">
                <span x-text="t.label"></span>
            </button>
        </template>
    </div>
</div>

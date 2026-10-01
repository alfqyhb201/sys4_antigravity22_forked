<script>
/**
 * TrueERP - FileUpload Clipboard Paste Handler
 * يتيح للمصمم والمستخدمين لصق الصور مباشرة من الحافظة (Ctrl + V / Cmd + V) في حقول رفع الملفات ونوافذ الرفع.
 */
(function () {
    'use strict';

    function getOpenModal() {
        const modalSelectors = [
            '.fi-modal-window',
            '.fi-modal:not(.fi-hidden)',
            '[role="dialog"][aria-modal="true"]',
            '[role="dialog"]',
            '.fi-modal'
        ];

        const modals = document.querySelectorAll(modalSelectors.join(', '));
        for (let i = modals.length - 1; i >= 0; i--) {
            const modal = modals[i];
            if (modal.classList.contains('fi-hidden') || modal.hasAttribute('hidden')) {
                continue;
            }
            const style = window.getComputedStyle(modal);
            if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
                continue;
            }
            if (modal.offsetParent === null && style.position !== 'fixed' && style.position !== 'absolute') {
                continue;
            }
            return modal;
        }
        return null;
    }

    function findTargetUploadContainer(eventTarget) {
        // 1. إذا كان مؤشر الفأرة أو التركيز داخل حقل رفع
        if (eventTarget) {
            const directContainer = eventTarget.closest('.fi-fo-file-upload');
            if (directContainer) {
                return directContainer;
            }
        }

        // 2. إذا كانت نافذة منبثقة (Modal) مفتوحة، نبحث عن أول حقل رفع داخلها
        const openModal = getOpenModal();
        if (openModal) {
            const modalUpload = openModal.querySelector('.fi-fo-file-upload');
            if (modalUpload) {
                return modalUpload;
            }
        }

        // 3. التحقق من أي حقل رفع يتم التمرير فوقه (Hover)
        const hovered = document.querySelector('.fi-fo-file-upload:hover');
        if (hovered) {
            return hovered;
        }

        // 4. إذا كان هناك حقل رفع واحد فقط ظاهر في الصفحة
        const visibleUploads = Array.from(document.querySelectorAll('.fi-fo-file-upload')).filter(el => {
            const style = window.getComputedStyle(el);
            return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
        });

        if (visibleUploads.length === 1) {
            return visibleUploads[0];
        }

        return null;
    }

    function getPondInstance(uploadContainer) {
        if (!uploadContainer) return null;

        // 1. فحص بيانات Alpine.js
        if (window.Alpine) {
            try {
                const data = window.Alpine.$data(uploadContainer);
                if (data && data.pond) {
                    return data.pond;
                }
            } catch (e) {}
        }

        // 2. فحص FilePond global API
        if (window.FilePond) {
            try {
                const input = uploadContainer.querySelector('input[type="file"]');
                if (input) {
                    const pond = window.FilePond.find(input);
                    if (pond) return pond;
                }
                const pondRoot = uploadContainer.querySelector('.filepond--root');
                if (pondRoot) {
                    const pond = window.FilePond.find(pondRoot);
                    if (pond) return pond;
                }
                const pond = window.FilePond.find(uploadContainer);
                if (pond) return pond;
            } catch (e) {}
        }

        return null;
    }

    function extractImageFiles(e) {
        const clipboardData = e.clipboardData || window.clipboardData;
        if (!clipboardData) return [];

        const files = [];

        // أ) التحقق من الملفات المنسوخة مباشرة من نظام التشغيل (Windows Explorer / Finder)
        if (clipboardData.files && clipboardData.files.length > 0) {
            for (let i = 0; i < clipboardData.files.length; i++) {
                const file = clipboardData.files[i];
                if (file && (file.type.startsWith('image/') || /\.(png|jpe?g|gif|webp|svg|psd|ai|pdf)$/i.test(file.name))) {
                    files.push(file);
                }
            }
        }

        // ب) التحقق من عناصر الحافظة (لقطات الشاشة Screenshot / Photoshop / أداة القصاصة / نسخ الصور من المتصفح)
        if (files.length === 0 && clipboardData.items && clipboardData.items.length > 0) {
            for (let i = 0; i < clipboardData.items.length; i++) {
                const item = clipboardData.items[i];
                if (item.kind === 'file' || item.type.startsWith('image/')) {
                    const blob = item.getAsFile();
                    if (blob) {
                        let filename = blob.name;
                        if (!filename || filename === 'image.png' || filename === 'blob') {
                            const ext = (blob.type && blob.type.split('/')[1]) || 'png';
                            const safeExt = ext === 'jpeg' ? 'jpg' : ext;
                            const now = new Date();
                            const pad = n => String(n).padStart(2, '0');
                            const timestamp = `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}_${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`;
                            filename = `design_paste_${timestamp}.${safeExt}`;
                        }
                        const namedFile = new File([blob], filename, {
                            type: blob.type || 'image/png',
                            lastModified: Date.now(),
                        });
                        files.push(namedFile);
                    }
                }
            }
        }

        return files;
    }

    function showPasteFeedback(container) {
        if (!container) return;
        const originalShadow = container.style.boxShadow;
        container.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        container.style.boxShadow = '0 0 0 4px rgba(16, 185, 129, 0.6), 0 0 20px rgba(16, 185, 129, 0.2)';
        container.style.transform = 'scale(1.01)';
        
        setTimeout(() => {
            container.style.boxShadow = originalShadow;
            container.style.transform = '';
        }, 600);
    }

    window.addEventListener('paste', function (e) {
        const imageFiles = extractImageFiles(e);

        // إذا لم تكن هناك صور في الحافظة، نترك اللصق النصي العادي يعمل دون أي اعتراض
        if (imageFiles.length === 0) {
            return;
        }

        // البحث عن حقل الرفع المستهدف
        const uploadContainer = findTargetUploadContainer(e.target);
        if (!uploadContainer) {
            return;
        }

        const pond = getPondInstance(uploadContainer);
        if (!pond) {
            return;
        }

        // إيقاف السلوك الافتراضي لمنع إدراج نصوص عشوائية في حقول الإدخال
        e.preventDefault();
        e.stopPropagation();

        showPasteFeedback(uploadContainer);

        const isMultiple = pond.allowMultiple ?? false;

        if (!isMultiple) {
            // رفع ملف فردي: استبدال أي ملف سابق
            const existingFiles = pond.getFiles();
            if (existingFiles && existingFiles.length > 0) {
                pond.removeFile(0);
            }
            pond.addFile(imageFiles[0]).catch(err => {
                console.error('[TrueERP Paste] Error adding file:', err);
            });
        } else {
            // رفع ملفات متعددة: إضافة كل الصور المنسوخة
            imageFiles.forEach(file => {
                pond.addFile(file).catch(err => {
                    console.error('[TrueERP Paste] Error adding file:', err);
                });
            });
        }
    }, true);
})();
</script>

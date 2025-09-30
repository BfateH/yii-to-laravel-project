<div class="form-group">
    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        class="form-control"
    >{{ old($name, $value) }}</textarea>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.ClassicEditor) {
            const textarea = document.querySelector('#{{ $id }}');
            if (!textarea) return;
            let initialValue = textarea.value;
            if(initialValue === "0") {
                textarea.value = '';
            }

            ClassicEditor
                .create(document.querySelector('#{{ $id }}'), {
                    licenseKey: 'GPL',
                    toolbar: [
                        'heading', '|',
                        'bold', 'italic', 'link', '|',
                        'bulletedList', 'numberedList', '|',
                        'outdent', 'indent', '|',
                        'imageUpload', 'blockQuote', 'insertTable', 'mediaEmbed', 'undo', 'redo'
                    ],
                    image: {
                        toolbar: [
                            'imageTextAlternative',
                        ]
                    },
                    simpleUpload: {
                        uploadUrl: '{{ $uploadUrl }}',
                        withCredentials: true,
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    }
                })
                .then(editor => {
                    editor.model.document.on('change:data', () => {
                        editor.updateSourceElement();
                    });

                    const form = document.querySelector('#{{ $id }}').closest('form');
                    if (form) {
                        form.addEventListener('submit', () => {
                            editor.updateSourceElement();
                        });
                    }
                })
                .catch(error => {
                    console.error(error);
                });
        } else {
            console.error('CKEditor not loaded');
        }
    });
</script>

<style>
    /* Тёмная тема для CKEditor */
    .ck.ck-editor {
        --ck-color-base-foreground: #2d3748;
        --ck-color-base-background: #2d3748;
        --ck-color-text: #e2e8f0;
        --ck-color-shadow-drop: #1a202c;
        --ck-color-shadow-inner: #1a202c;
        --ck-color-base-border: #4a5568;
        --ck-color-base-focus: #63b3ed;
        --ck-color-button-default-hover-background: #4a5568;
        --ck-color-button-default-active-background: #4a5568;
        --ck-color-input-background: #4a5568;
        --ck-color-input-border: #4a5568;
        --ck-color-input-text: #e2e8f0;
        --ck-color-list-button-on-background: #4a5568;
        --ck-color-list-button-on-text: #e2e8f0;
        --ck-color-list-button-on-border: #4a5568;
        --ck-color-panel-background: #2d3748;
        --ck-color-panel-border: #4a5568;
        --ck-color-toolbar-background: #2d3748;
        --ck-color-toolbar-border: #4a5568;
        --ck-color-tooltip-background: #2d3748;
        --ck-color-tooltip-text: #e2e8f0;
        --ck-color-list-background: #2d3748;
        --ck-color-list-text: #e2e8f0;
        --ck-color-list-border: #4a5568;
        --ck-color-split-button-hover-background: #4a5568;
        --ck-color-split-button-active-background: #4a5568;
        --ck-color-split-button-disabled-background: #2d3748;
        --ck-color-split-button-disabled-border: #4a5568;
        --ck-color-split-button-focused-background: #4a5568;
        --ck-color-split-button-focused-border: #63b3ed;
        --ck-color-split-button-text: #e2e8f0;
        --ck-color-split-button-hover-text: #e2e8f0;
        --ck-color-split-button-active-text: #e2e8f0;
        --ck-color-split-button-disabled-text: #a0aec0;
        --ck-color-split-button-focused-text: #e2e8f0;
        --ck-color-split-button-border: #4a5568;
        --ck-color-split-button-hover-border: #63b3ed;
        --ck-color-split-button-active-border: #63b3ed;
        --ck-color-split-button-background: #2d3748;
    }

    .ck.ck-content {
        background-color: #2d3748 !important;
        color: #e2e8f0 !important;
        border-color: #4a5568 !important;
        min-height: 300px;
        padding: 10px !important;
        box-sizing: border-box !important;
        margin: 0 !important;
    }

    .ck.ck-editor__editable_inline > * {
        color: #e2e8f0 !important;
        margin: 0 !important;
        padding: 0 !important;
        box-sizing: border-box !important;
    }

    .ck.ck-editor__editable {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
        min-height: 300px;
        padding: 10px !important;
        box-sizing: border-box !important;
        margin: 0 !important;
    }

    .ck.ck-editor__main {
        padding: 0 !important;
        margin: 0 !important;
    }

    .ck.ck-content ul,
    .ck.ck-content ol {
        padding-left: 20px !important;
        margin: 0 !important;
    }

    .ck.ck-content li {
        margin: 0 !important;
        padding: 0 !important;
        box-sizing: border-box !important;
    }

    .ck.ck-toolbar {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
    }

    .ck.ck-button {
        color: #e2e8f0 !important;
        background-color: #2d3748 !important;
    }

    .ck.ck-button:hover {
        background-color: #4a5568 !important;
    }

    .ck.ck-button.ck-on {
        background-color: #4a5568 !important;
        color: #e2e8f0 !important;
    }

    .ck.ck-dropdown__panel {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
    }

    .ck.ck-list {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
    }

    .ck.ck-list__item {
        background-color: #2d3748 !important;
    }

    .ck.ck-list__item .ck-button {
        color: #e2e8f0 !important;
    }

    .ck.ck-list__item .ck-button:hover {
        background-color: #4a5568 !important;
    }

    .ck.ck-list__item .ck-button.ck-on {
        background-color: #4a5568 !important;
    }

    .ck.ck-input-text {
        background-color: #4a5568 !important;
        color: #e2e8f0 !important;
        border-color: #4a5568 !important;
    }

    .ck.ck-input-text:focus {
        border-color: #63b3ed !important;
    }

    .ck.ck-balloon-panel {
        background-color: #2d3748 !important;
        border-color: #4a5568 !important;
        color: #e2e8f0 !important;
    }

    .ck.ck-balloon-panel .ck-button {
        color: #e2e8f0 !important;
    }

    .ck.ck-balloon-panel .ck-button:hover {
        background-color: #4a5568 !important;
    }

    .ck.ck-split-button__arrow {
        color: #e2e8f0 !important;
    }

    .ck.ck-split-button__action {
        border-right-color: #4a5568 !important;
    }

    .ck.ck-split-button__arrow {
        border-left-color: #4a5568 !important;
    }

    .ck.ck-split-button .ck-button.ck-on {
        background-color: #4a5568 !important;
    }

    .ck.ck-split-button .ck-button:hover {
        background-color: #4a5568 !important;
    }

    .ck.ck-split-button .ck-button {
        background-color: #2d3748 !important;
        color: #e2e8f0 !important;
    }
</style>

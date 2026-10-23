@extends('layouts.entry')

@section('title', 'Imbas dokumen')

@section('content')
@php
    $selectedType = App\Models\LegalFile::TYPE_SNP;
@endphp
<div
    class="entry-home"
    data-entry-flow
    data-analyze-url="{{ route('entry.scan.store') }}"
    data-max-pages="3"
>
    <div data-entry-heading>
        <p class="entry-stat__label" data-entry-step-label>Langkah 1 / 3</p>
        <h1 class="entry-page-title" data-entry-step-title>Ambil gambar</h1>
        <p class="entry-page-lede" data-entry-step-lede>Ambil gambar dengan kamera, atau muat naik gambar dokumen. Boleh sampai 3 muka surat.</p>
    </div>

    <input type="file" accept="image/*" capture="environment" hidden data-entry-camera-input>
    <input type="file" accept="image/*" multiple hidden data-entry-upload-input>

    {{-- Step 1: camera or upload --}}
    <div class="entry-camera__panel" data-entry-step="1">
        <div class="entry-camera__actions">
            <button class="entry-auth__submit" type="button" data-entry-open-camera>Ambil gambar</button>
            <button class="entry-app__logout" type="button" data-entry-open-upload>Muat naik dokumen</button>
        </div>
    </div>

    {{-- Step 1: camera --}}
    <div class="entry-camera__panel hidden" data-entry-step="1-live">
        <div class="entry-camera__stage">
            <video class="entry-camera__video" data-entry-camera-video playsinline muted autoplay></video>
            <p class="entry-camera__status" data-entry-camera-status aria-live="polite">Halakan kamera ke dokumen.</p>
        </div>
        <div class="entry-camera__actions">
            <button class="entry-auth__submit" type="button" data-entry-camera-shutter>Tangkap</button>
            <button class="entry-app__logout" type="button" data-entry-cancel-camera>Batal</button>
        </div>
    </div>

    <div class="entry-camera__panel hidden" data-entry-step="1-fallback">
        <p class="entry-page-lede">Kamera tidak dapat dibuka. Muat naik gambar dokumen, atau cuba lagi melalui telefon.</p>
        <div class="entry-camera__actions">
            <button class="entry-auth__submit" type="button" data-entry-open-upload>Muat naik dokumen</button>
            <button class="entry-app__logout" type="button" data-entry-camera-fallback-btn>Ambil gambar</button>
            <button class="entry-app__logout" type="button" data-entry-back-idle>Kembali</button>
        </div>
    </div>

    {{-- Step 1: review pages before AI --}}
    <div class="entry-camera__panel hidden" data-entry-step="1-pages">
        <ul class="entry-camera__thumbs" data-entry-page-thumbs></ul>
        <p class="entry-page-lede" data-entry-page-count></p>
        <div class="entry-camera__actions">
            <button class="entry-auth__submit" type="button" data-entry-analyze>Baca dengan AI</button>
            <button class="entry-app__logout" type="button" data-entry-add-page>Tambah muka surat</button>
            <button class="entry-app__logout" type="button" data-entry-clear-pages>Mulakan semula</button>
        </div>
    </div>

    {{-- Step 2: AI --}}
    <div class="entry-camera__panel hidden" data-entry-step="2">
        <div class="entry-process">
            <div class="entry-process__spinner" aria-hidden="true"></div>
            <p class="entry-process__title">AI sedang membaca dokumen…</p>
            <p class="entry-process__text">Sila tunggu sebentar.</p>
        </div>
    </div>

    {{-- Step 3: form --}}
    <div class="entry-camera__panel hidden" data-entry-step="3">
        <p class="entry-app__flash entry-app__flash--warn hidden" data-entry-ai-warning></p>
        <form class="space-y-6" method="POST" action="{{ $saveUrl }}" data-case-type-form data-entry-save-form>
            @csrf
            <input type="hidden" name="temp_path" value="" data-entry-temp-path>
            @include('entry.partials.case-form-fields', [
                'categories' => $categories,
                'suggestion' => [],
                'selectedType' => $selectedType,
            ])
            <button class="btn-primary min-h-14 w-full" type="submit">Simpan kes</button>
        </form>
    </div>
</div>

<script>
(() => {
    const root = document.querySelector('[data-entry-flow]');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const analyzeUrl = root.getAttribute('data-analyze-url');
    const maxPages = Math.max(1, Number(root.getAttribute('data-max-pages') || 3));
    const cameraInput = root.querySelector('[data-entry-camera-input]');
    const uploadInput = root.querySelector('[data-entry-upload-input]');
    const video = root.querySelector('[data-entry-camera-video]');
    const status = root.querySelector('[data-entry-camera-status]');
    const openBtn = root.querySelector('[data-entry-open-camera]');
    const uploadBtns = root.querySelectorAll('[data-entry-open-upload]');
    const shutter = root.querySelector('[data-entry-camera-shutter]');
    const cancelCamera = root.querySelector('[data-entry-cancel-camera]');
    const fallbackBtn = root.querySelector('[data-entry-camera-fallback-btn]');
    const backIdle = root.querySelector('[data-entry-back-idle]');
    const thumbsEl = root.querySelector('[data-entry-page-thumbs]');
    const pageCountEl = root.querySelector('[data-entry-page-count]');
    const analyzeBtn = root.querySelector('[data-entry-analyze]');
    const addPageBtn = root.querySelector('[data-entry-add-page]');
    const clearPagesBtn = root.querySelector('[data-entry-clear-pages]');
    const saveForm = root.querySelector('[data-entry-save-form]');
    const tempPathInput = root.querySelector('[data-entry-temp-path]');
    const warningEl = root.querySelector('[data-entry-ai-warning]');
    const stepLabel = root.querySelector('[data-entry-step-label]');
    const stepTitle = root.querySelector('[data-entry-step-title]');
    const stepLede = root.querySelector('[data-entry-step-lede]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const panels = {
        '1': root.querySelector('[data-entry-step="1"]'),
        '1-live': root.querySelector('[data-entry-step="1-live"]'),
        '1-fallback': root.querySelector('[data-entry-step="1-fallback"]'),
        '1-pages': root.querySelector('[data-entry-step="1-pages"]'),
        '2': root.querySelector('[data-entry-step="2"]'),
        '3': root.querySelector('[data-entry-step="3"]'),
    };

    if (!analyzeUrl || !cameraInput || !uploadInput || !video || !saveForm || !openBtn || !thumbsEl) return;

    let stream = null;
    /** @type {{ file: File, url: string }[]} */
    let pages = [];

    const copy = {
        '1': { label: 'Langkah 1 / 3', title: 'Ambil gambar', lede: 'Ambil gambar dengan kamera, atau muat naik gambar dokumen. Boleh sampai 3 muka surat.' },
        '1-live': { label: 'Langkah 1 / 3', title: 'Ambil gambar', lede: 'Halakan kamera ke dokumen, kemudian tekan Tangkap.' },
        '1-fallback': { label: 'Langkah 1 / 3', title: 'Ambil gambar', lede: 'Kamera tidak dapat dibuka. Muat naik gambar dokumen, atau cuba lagi melalui telefon.' },
        '1-pages': { label: 'Langkah 1 / 3', title: 'Semak muka surat', lede: 'Tambah muka surat jika maklumat tidak lengkap pada muka pertama, kemudian baca dengan AI.' },
        '2': { label: 'Langkah 2 / 3', title: 'AI membaca', lede: 'Dokumen sedang diproses. Sila tunggu.' },
        '3': { label: 'Langkah 3 / 3', title: 'Semak borang', lede: 'Semak maklumat yang diisi AI, kemudian simpan.' },
    };

    const showStep = (name) => {
        Object.entries(panels).forEach(([key, panel]) => {
            panel?.classList.toggle('hidden', key !== name);
        });
        const text = copy[name] || copy['1'];
        if (stepLabel) stepLabel.textContent = text.label;
        if (stepTitle) stepTitle.textContent = text.title;
        if (stepLede) stepLede.textContent = text.lede;
    };

    const stopCamera = () => {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
    };

    const clearPages = () => {
        pages.forEach((page) => URL.revokeObjectURL(page.url));
        pages = [];
    };

    const renderPages = () => {
        thumbsEl.innerHTML = '';
        pages.forEach((page, index) => {
            const li = document.createElement('li');
            li.className = 'entry-camera__thumb';
            const img = document.createElement('img');
            img.src = page.url;
            img.alt = `Muka surat ${index + 1}`;
            const label = document.createElement('span');
            label.textContent = String(index + 1);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'entry-camera__thumb-remove';
            remove.setAttribute('aria-label', `Buang muka surat ${index + 1}`);
            remove.textContent = '×';
            remove.addEventListener('click', (e) => {
                e.preventDefault();
                URL.revokeObjectURL(page.url);
                pages.splice(index, 1);
                if (pages.length === 0) {
                    goIdle();
                    return;
                }
                renderPages();
            });
            li.append(img, label, remove);
            thumbsEl.appendChild(li);
        });
        if (pageCountEl) {
            pageCountEl.textContent = pages.length >= maxPages
                ? `${pages.length} / ${maxPages} muka surat (maksimum).`
                : `${pages.length} / ${maxPages} muka surat. Tambah lagi jika perlu.`;
        }
        if (addPageBtn) addPageBtn.disabled = pages.length >= maxPages;
        if (analyzeBtn) analyzeBtn.disabled = pages.length === 0;
    };

    const showPages = () => {
        stopCamera();
        renderPages();
        showStep('1-pages');
    };

    const addFiles = (fileList) => {
        const incoming = Array.from(fileList || []).filter((f) => f && f.type.startsWith('image/'));
        for (const file of incoming) {
            if (pages.length >= maxPages) break;
            pages.push({ file, url: URL.createObjectURL(file) });
        }
        if (pages.length === 0) return;
        showPages();
    };

    const goIdle = () => {
        stopCamera();
        clearPages();
        cameraInput.value = '';
        uploadInput.value = '';
        showStep('1');
    };

    const openUpload = () => {
        stopCamera();
        uploadInput.value = '';
        uploadInput.click();
    };

    const startCamera = async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            showStep('1-fallback');
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'environment' } },
            });
            video.srcObject = stream;
            await video.play();
            if (status) {
                status.textContent = pages.length
                    ? `Muka surat ${pages.length + 1}. Halakan kamera ke dokumen.`
                    : 'Halakan kamera ke dokumen.';
            }
            showStep('1-live');
        } catch (err) {
            stopCamera();
            showStep('1-fallback');
        }
    };

    const borrowersRoot = () => saveForm.querySelector('[data-borrowers-root]');
    const coBorrowersEl = () => saveForm.querySelector('[data-co-borrowers]');
    const borrowerTemplate = () => saveForm.querySelector('[data-borrower-template]');

    const nextBorrowerIndex = () => {
        const rootEl = borrowersRoot();
        if (!rootEl) return 1;
        return rootEl.querySelectorAll('[data-borrower-row]').length;
    };

    const addBorrowerRow = (name = '', ic = '') => {
        const list = coBorrowersEl();
        const tpl = borrowerTemplate();
        if (!list || !tpl) return;
        const index = nextBorrowerIndex();
        const html = tpl.innerHTML.replaceAll('__INDEX__', String(index));
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const row = wrap.firstElementChild;
        if (!row) return;
        const nameInput = row.querySelector(`[name="borrowers[${index}][name]"]`);
        const icInput = row.querySelector(`[name="borrowers[${index}][ic_number]"]`);
        if (nameInput) nameInput.value = name || '';
        if (icInput) icInput.value = ic || '';
        list.appendChild(row);
    };

    const fillBorrowers = (draft) => {
        const list = coBorrowersEl();
        if (list) list.innerHTML = '';
        const primaryName = saveForm.querySelector('[data-entry-field="borrower_name_0"]');
        const primaryIc = saveForm.querySelector('[data-entry-field="borrower_ic_0"]');
        let borrowers = Array.isArray(draft.borrowers) ? draft.borrowers : [];
        if (borrowers.length === 0 && draft.purchaser) {
            borrowers = [{ name: draft.purchaser, ic_number: null }];
        }
        const first = borrowers[0] || { name: '', ic_number: null };
        if (primaryName && !primaryName.disabled) primaryName.value = first.name || '';
        if (primaryIc && !primaryIc.disabled) primaryIc.value = first.ic_number || '';
        borrowers.slice(1).forEach((b) => addBorrowerRow(b?.name || '', b?.ic_number || ''));
    };

    const fillDraft = (draft) => {
        const typeSelect = saveForm.querySelector('[data-case-type-select]');
        if (typeSelect && draft.case_type) {
            typeSelect.value = draft.case_type;
            typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const setField = (name, value) => {
            saveForm.querySelectorAll(`[data-entry-field="${name}"]`).forEach((el) => {
                if (el.disabled) return;
                el.value = value == null ? '' : String(value);
            });
        };
        setField('reference_number', draft.reference_number);
        setField('case_category_id', draft.case_category_id);
        setField('purchaser', draft.purchaser);
        setField('vendor', draft.vendor);
        setField('bank', draft.bank);
        setField('property', draft.property);
        setField('title_number', draft.title_number);
        setField('suite_number', draft.suite_number);
        setField('ic_number', draft.ic_number);
        setField('start_date', draft.start_date);
        setField('end_date', draft.end_date);
        fillBorrowers(draft || {});
    };

    const processWithAi = async () => {
        if (pages.length === 0) return;
        stopCamera();
        showStep('2');
        const body = new FormData();
        pages.forEach((page) => body.append('documents[]', page.file));
        body.append('_token', csrf);
        try {
            const response = await fetch(analyzeUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
                credentials: 'same-origin',
            });
            if (!response.ok) throw new Error('analyze_failed');
            const payload = await response.json();
            if (payload.save_url) saveForm.action = payload.save_url;
            if (tempPathInput) tempPathInput.value = payload.temp_path || '';
            fillDraft(payload.draft || {});
            if (warningEl) {
                if (payload.warning) {
                    warningEl.textContent = payload.warning;
                    warningEl.classList.remove('hidden');
                } else {
                    warningEl.textContent = '';
                    warningEl.classList.add('hidden');
                }
            }
            clearPages();
            showStep('3');
        } catch (err) {
            showPages();
            window.alert('Gagal baca dokumen. Sila cuba lagi.');
        }
    };

    openBtn.addEventListener('click', (e) => {
        e.preventDefault();
        startCamera();
    });
    uploadBtns.forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openUpload();
        });
    });
    cancelCamera?.addEventListener('click', (e) => {
        e.preventDefault();
        if (pages.length) {
            showPages();
            return;
        }
        goIdle();
    });
    backIdle?.addEventListener('click', (e) => { e.preventDefault(); goIdle(); });
    analyzeBtn?.addEventListener('click', (e) => { e.preventDefault(); processWithAi(); });
    addPageBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        if (pages.length >= maxPages) return;
        startCamera();
    });
    clearPagesBtn?.addEventListener('click', (e) => { e.preventDefault(); goIdle(); });
    shutter?.addEventListener('click', (e) => {
        e.preventDefault();
        if (!video.videoWidth) {
            if (status) status.textContent = 'Kamera belum sedia. Cuba lagi.';
            return;
        }
        if (pages.length >= maxPages) {
            showPages();
            return;
        }
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d')?.drawImage(video, 0, 0);
        canvas.toBlob((blob) => {
            if (!blob) {
                if (status) status.textContent = 'Gagal tangkap gambar. Cuba lagi.';
                return;
            }
            addFiles([new File([blob], `entry-scan-${Date.now()}.jpg`, { type: 'image/jpeg' })]);
        }, 'image/jpeg', 0.92);
    });
    fallbackBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        cameraInput.value = '';
        cameraInput.click();
    });
    cameraInput.addEventListener('change', () => {
        if (cameraInput.files?.length) addFiles(cameraInput.files);
    });
    uploadInput.addEventListener('change', () => {
        if (uploadInput.files?.length) addFiles(uploadInput.files);
    });

    showStep('1');
    window.addEventListener('pagehide', () => {
        stopCamera();
        clearPages();
    });
})();
</script>
@endsection

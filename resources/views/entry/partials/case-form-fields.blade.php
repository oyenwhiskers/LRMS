@php
    $suggestion = $suggestion ?? [];
    $selectedType = $selectedType ?? App\Models\LegalFile::TYPE_SNP;
    $useOld = $useOld ?? true;
    $layout = $layout ?? 'default';
    $isModalLayout = $layout === 'modal';
    $field = function (string $key, mixed $fallback = '') use ($useOld, $suggestion): mixed {
        if ($useOld) {
            return old($key, $suggestion[$key] ?? $fallback);
        }

        return $suggestion[$key] ?? $fallback;
    };
    $sectionClass = $isModalLayout
        ? 'entry-case-fields entry-case-fields--modal'
        : 'entry-case-fields space-y-4 border border-[#12352f]/15 bg-white p-4';
    $fieldClass = $isModalLayout ? 'entry-case-fields__field' : 'block space-y-1';
    $borrowerCardClass = $isModalLayout
        ? 'entry-case-fields__party'
        : 'grid gap-3 rounded border border-[#12352f]/10 p-3';
@endphp

<section class="{{ $sectionClass }}">
    <label class="{{ $fieldClass }}">
        <span class="form-label">No. rujukan fail</span>
        <input
            class="form-input font-mono"
            name="reference_number"
            value="{{ $field('reference_number') }}"
            required
            autocomplete="off"
            placeholder="cth. THY/MW/ABMB/2024/02"
            data-entry-field="reference_number"
        >
        @unless ($isModalLayout)
            <span class="form-help">Ambil dari dokumen. Medan lain boleh kosong — isi kemudian jika tiada pada muka depan.</span>
        @endunless
        @if ($useOld)
            @error('reference_number')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        @endif
    </label>

    <label class="{{ $fieldClass }}">
        <span class="form-label">Jenis kes</span>
        <select class="form-input" name="case_type" required data-case-type-select data-entry-field="case_type">
            <option value="">Pilih jenis kes</option>
            <option value="{{ App\Models\LegalFile::TYPE_LOAN }}" @selected($selectedType === App\Models\LegalFile::TYPE_LOAN)>Pinjaman</option>
            <option value="{{ App\Models\LegalFile::TYPE_SNP }}" @selected($selectedType === App\Models\LegalFile::TYPE_SNP)>Jual beli (S&amp;P)</option>
            <option value="{{ App\Models\LegalFile::TYPE_LITIGATION }}" @selected($selectedType === App\Models\LegalFile::TYPE_LITIGATION)>Litigasi</option>
        </select>
        @if ($useOld)
            @error('case_type')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        @endif
    </label>

    <label class="{{ $fieldClass }}">
        <span class="form-label">Kategori kes</span>
        <select class="form-input" name="case_category_id" required data-entry-field="case_category_id">
            <option value="">Pilih kategori</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) $field('case_category_id') === (string) $category->id)>
                    {{ $category->code }} — {{ $category->name }}
                </option>
            @endforeach
        </select>
        @if ($useOld)
            @error('case_category_id')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        @endif
    </label>

    @php
        $oldBorrowers = $field('borrowers', $suggestion['borrowers'] ?? []);
        if (! is_array($oldBorrowers) || $oldBorrowers === []) {
            $joined = $field('purchaser', $suggestion['purchaser'] ?? '');
            $oldBorrowers = $joined !== '' ? [['name' => $joined, 'ic_number' => '']] : [['name' => '', 'ic_number' => '']];
        }
        $mainBorrower = $oldBorrowers[0] ?? ['name' => '', 'ic_number' => ''];
        $coBorrowers = array_values(array_slice($oldBorrowers, 1));
    @endphp
    <div @class([$isModalLayout ? 'entry-case-fields__panel' : 'grid gap-4', 'hidden' => $selectedType !== App\Models\LegalFile::TYPE_LOAN]) data-case-type-panel="loan">
        <div @class([$isModalLayout ? 'entry-case-fields__parties' : 'space-y-3']) data-borrowers-root>
            <div class="{{ $borrowerCardClass }}" data-borrower-row data-borrower-primary>
                <p class="entry-case-fields__party-title">Peminjam utama <span class="font-normal text-stone-500">(pihak 1)</span></p>
                <label class="{{ $fieldClass }}">
                    <span class="form-label">Nama</span>
                    <input class="form-input" name="borrowers[0][name]" value="{{ $mainBorrower['name'] ?? '' }}" data-entry-field="borrower_name_0" autocomplete="off" @disabled($selectedType !== App\Models\LegalFile::TYPE_LOAN)>
                </label>
                <label class="{{ $fieldClass }}">
                    <span class="form-label">No. KP</span>
                    <input class="form-input" name="borrowers[0][ic_number]" value="{{ $mainBorrower['ic_number'] ?? '' }}" data-entry-field="borrower_ic_0" autocomplete="off" @disabled($selectedType !== App\Models\LegalFile::TYPE_LOAN)>
                </label>
            </div>
            <div @class([$isModalLayout ? 'entry-case-fields__parties' : 'space-y-3']) data-co-borrowers>
                @foreach($coBorrowers as $index => $co)
                    @php $i = $index + 1; @endphp
                    <div class="{{ $borrowerCardClass }}" data-borrower-row>
                        <p class="entry-case-fields__party-title">Peminjam lain</p>
                        <label class="{{ $fieldClass }}">
                            <span class="form-label">Nama</span>
                            <input class="form-input" name="borrowers[{{ $i }}][name]" value="{{ $co['name'] ?? '' }}" data-entry-field="borrower_name_{{ $i }}" autocomplete="off" @disabled($selectedType !== App\Models\LegalFile::TYPE_LOAN)>
                        </label>
                        <label class="{{ $fieldClass }}">
                            <span class="form-label">No. KP</span>
                            <input class="form-input" name="borrowers[{{ $i }}][ic_number]" value="{{ $co['ic_number'] ?? '' }}" data-entry-field="borrower_ic_{{ $i }}" autocomplete="off" @disabled($selectedType !== App\Models\LegalFile::TYPE_LOAN)>
                        </label>
                    </div>
                @endforeach
            </div>
            <button class="entry-app__logout" type="button" data-add-borrower>Tambah peminjam</button>
            <span class="form-help">Semua nama akan dipaparkan pada label fail (disambung dengan &amp;).</span>
            <template data-borrower-template>
                <div class="{{ $borrowerCardClass }}" data-borrower-row>
                    <p class="entry-case-fields__party-title">Peminjam lain</p>
                    <label class="{{ $fieldClass }}">
                        <span class="form-label">Nama</span>
                        <input class="form-input" name="borrowers[__INDEX__][name]" value="" data-entry-field="borrower_name___INDEX__" autocomplete="off">
                    </label>
                    <label class="{{ $fieldClass }}">
                        <span class="form-label">No. KP</span>
                        <input class="form-input" name="borrowers[__INDEX__][ic_number]" value="" data-entry-field="borrower_ic___INDEX__" autocomplete="off">
                    </label>
                </div>
            </template>
        </div>
        <label class="{{ $fieldClass }}">
            <span class="form-label">Bank</span>
            <input class="form-input" name="bank" value="{{ $field('bank') }}" data-entry-field="bank" @disabled($selectedType !== App\Models\LegalFile::TYPE_LOAN)>
        </label>
        <label class="{{ $fieldClass }}">
            <span class="form-label">Alamat hartanah</span>
            <textarea class="form-input min-h-24" name="property" data-entry-field="property" @disabled($selectedType !== App\Models\LegalFile::TYPE_LOAN)>{{ $field('property') }}</textarea>
        </label>
        <label class="{{ $fieldClass }}">
            <span class="form-label">No. hakmilik</span>
            <input class="form-input" name="title_number" value="{{ $field('title_number') }}" data-entry-field="title_number" @disabled($selectedType !== App\Models\LegalFile::TYPE_LOAN)>
        </label>
    </div>

    <div @class([$isModalLayout ? 'entry-case-fields__panel' : 'grid gap-4', 'hidden' => $selectedType !== App\Models\LegalFile::TYPE_SNP]) data-case-type-panel="snp">
        <label class="{{ $fieldClass }}">
            <span class="form-label">Pembeli</span>
            <input class="form-input" name="purchaser" value="{{ $field('purchaser') }}" data-entry-field="purchaser" @disabled($selectedType !== App\Models\LegalFile::TYPE_SNP)>
        </label>
        <label class="{{ $fieldClass }}">
            <span class="form-label">Penjual</span>
            <input class="form-input" name="vendor" value="{{ $field('vendor') }}" data-entry-field="vendor" @disabled($selectedType !== App\Models\LegalFile::TYPE_SNP)>
        </label>
        <label class="{{ $fieldClass }}">
            <span class="form-label">Alamat hartanah</span>
            <textarea class="form-input min-h-24" name="property" data-entry-field="property" @disabled($selectedType !== App\Models\LegalFile::TYPE_SNP)>{{ $field('property') }}</textarea>
        </label>
        <label class="{{ $fieldClass }}">
            <span class="form-label">No. hakmilik</span>
            <input class="form-input" name="title_number" value="{{ $field('title_number') }}" data-entry-field="title_number" @disabled($selectedType !== App\Models\LegalFile::TYPE_SNP)>
        </label>
    </div>

    <div @class([$isModalLayout ? 'entry-case-fields__panel' : 'grid gap-4', 'hidden' => $selectedType !== App\Models\LegalFile::TYPE_LITIGATION]) data-case-type-panel="litigation">
        <label class="{{ $fieldClass }}">
            <span class="form-label">No. guaman</span>
            <input class="form-input" name="suite_number" value="{{ $field('suite_number') }}" data-entry-field="suite_number" @disabled($selectedType !== App\Models\LegalFile::TYPE_LITIGATION)>
        </label>
        <label class="{{ $fieldClass }}">
            <span class="form-label">Nama individu</span>
            <input class="form-input" name="purchaser" value="{{ $field('purchaser') }}" data-entry-field="purchaser" @disabled($selectedType !== App\Models\LegalFile::TYPE_LITIGATION)>
        </label>
        <label class="{{ $fieldClass }}">
            <span class="form-label">No. KP</span>
            <input class="form-input" name="ic_number" value="{{ $field('ic_number') }}" data-entry-field="ic_number" @disabled($selectedType !== App\Models\LegalFile::TYPE_LITIGATION)>
        </label>
    </div>

    <label class="{{ $fieldClass }}">
        <span class="form-label">Tarikh mula</span>
        <input class="form-input" type="date" name="start_date" value="{{ $field('start_date') }}" data-entry-field="start_date">
    </label>
    <label class="{{ $fieldClass }}">
        <span class="form-label">Tarikh tamat</span>
        <input class="form-input" type="date" name="end_date" value="{{ $field('end_date') }}" data-entry-field="end_date">
        @if ($useOld)
            @error('end_date')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
        @endif
    </label>
</section>

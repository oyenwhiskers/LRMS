@if(!empty($categories) && $categories->isNotEmpty())
    <fieldset class="dashboard-export__categories">
        <legend class="form-label">Categories</legend>
        <p class="dashboard-export__categories-hint">Leave unchecked to include all</p>
        <div class="dashboard-export__categories-list" role="group" aria-label="Filter by category">
            @foreach($categories as $category)
                <label class="dashboard-export__category">
                    <input type="checkbox" name="categories[]" value="{{ $category->id }}">
                    <span>
                        <span class="dashboard-export__category-code">{{ $category->code }}</span>
                        <span class="dashboard-export__category-name">{{ $category->name }}</span>
                    </span>
                </label>
            @endforeach
        </div>
    </fieldset>
@endif

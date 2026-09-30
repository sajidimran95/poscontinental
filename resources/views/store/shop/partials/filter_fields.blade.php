<div>
    <label class="font-bold block mb-2">Category</label>
    <select name="category" class="input-w">
        <option value="">All categories</option>
        @foreach($categories as $cat)
            <option value="{{ $cat->id }}" @selected(request('category') == $cat->id)>{{ $cat->name }}</option>
        @endforeach
    </select>
</div>
@if(($subcategories ?? collect())->count())
<div>
    <label class="font-bold block mb-2">Subcategory</label>
    <select name="sub" class="input-w">
        <option value="">All subcategories</option>
        @foreach($subcategories as $sub)
            <option value="{{ $sub->id }}" @selected(request('sub') == $sub->id)>{{ $sub->name }}</option>
        @endforeach
    </select>
</div>
@endif
@if($brands->count())
<div>
    <label class="font-bold block mb-2">Brand</label>
    <select name="brand" class="input-w">
        <option value="">All brands</option>
        @foreach($brands as $brand)
            <option value="{{ $brand->id }}" @selected(request('brand') === $brand->id)>{{ $brand->name }}</option>
        @endforeach
    </select>
</div>
@endif
<div>
    <label class="font-bold block mb-2">Price</label>
    <div class="grid grid-cols-2 gap-2">
        <input type="number" step="0.01" name="min_price" value="{{ request('min_price') }}" placeholder="Min" class="input-w">
        <input type="number" step="0.01" name="max_price" value="{{ request('max_price') }}" placeholder="Max" class="input-w">
    </div>
</div>
<div class="space-y-2">
    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="in_stock" value="1" @checked(request()->boolean('in_stock'))> In stock only</label>
    <label class="flex items-center gap-2 cursor-pointer"><input type="checkbox" name="new" value="1" @checked(request()->boolean('new'))> New arrivals</label>
</div>
<div>
    <label class="font-bold block mb-2">Sort</label>
    <select name="sort" class="input-w">
        <option value="newest" @selected(($sort ?? 'newest') === 'newest')>Newest</option>
        <option value="price_asc" @selected(($sort ?? '') === 'price_asc')>Price: Low to High</option>
        <option value="price_desc" @selected(($sort ?? '') === 'price_desc')>Price: High to Low</option>
        <option value="name" @selected(($sort ?? '') === 'name')>Name: A to Z</option>
    </select>
</div>
@if(request()->filled('q'))
    <input type="hidden" name="q" value="{{ request('q') }}">
@endif

{{-- Expects $imageUrl, $thumbUrl, $viewMode, $item_code from parent --}}
<div
    class="item-media-panel"
    x-data="{
        uploading: false,
        error: '',
        async upload(event, type) {
            const input = event.target;
            const file = input.files?.[0];
            input.value = '';
            if (!file) return;

            const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/x-ms-bmp'];
            if (file.type && !allowed.includes(file.type) && !/\.(jpe?g|png|gif|webp|bmp)$/i.test(file.name)) {
                this.error = 'Image must be JPG, PNG, GIF, WEBP, or BMP.';
                return;
            }
            if (file.size > 8 * 1024 * 1024) {
                this.error = 'Image must be 8 MB or smaller.';
                return;
            }

            this.uploading = true;
            this.error = '';

            try {
                const dataUrl = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = () => reject(new Error('read failed'));
                    reader.readAsDataURL(file);
                });
                await $wire.uploadItemMedia(dataUrl, file.name, type);
            } catch (e) {
                this.error = 'Upload failed. Try a smaller JPG/PNG file.';
            } finally {
                this.uploading = false;
            }
        }
    }"
>
    <div class="item-media">
        <div class="item-media-label">Image</div>
        @unless ($viewMode)
            <input
                type="file"
                accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,image/*"
                class="item-file"
                x-on:change="upload($event, 'image')"
            />
            <div class="item-hint" x-show="uploading" x-cloak>Uploading…</div>
            <p class="so-field-error" role="alert" x-show="error" x-text="error" x-cloak></p>
            @error('image_path') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
        @endunless
        @if ($imageUrl)
            <div class="item-preview-row">
                <div class="item-preview-frame {{ ! empty($compact) ? 'item-preview-frame-sm' : '' }}">
                    <img src="{{ $imageUrl }}" alt="{{ $item_code }}" class="item-preview" />
                </div>
                @unless ($viewMode)
                    <button type="button" wire:click="removeImage" class="desk-btn desk-btn-sm">Remove</button>
                @endunless
            </div>
        @elseif ($image_path)
            <p class="item-hint">Image file missing on disk ({{ $image_path }}).</p>
        @elseif ($viewMode)
            <p class="item-hint">No image.</p>
        @endif
    </div>

    <div class="item-media">
        <div class="item-media-label">Thumbnail</div>
        @unless ($viewMode)
            <input
                type="file"
                accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,image/*"
                class="item-file"
                x-on:change="upload($event, 'thumbnail')"
            />
            <div class="item-media-actions">
                <button type="button" wire:click="copyImageToThumbnail" class="desk-btn desk-btn-sm" @disabled(! $image_path)>
                    Copy from image
                </button>
            </div>
            @error('thumbnail_path') <p class="so-field-error" role="alert">{{ $message }}</p> @enderror
        @endunless
        @if ($thumbUrl)
            <div class="item-preview-row">
                <div class="item-preview-frame item-preview-frame-sm">
                    <img src="{{ $thumbUrl }}" alt="{{ $item_code }} thumbnail" class="item-preview" />
                </div>
                @unless ($viewMode)
                    <button type="button" wire:click="removeThumbnail" class="desk-btn desk-btn-sm">Remove</button>
                @endunless
            </div>
        @elseif ($thumbnail_path)
            <p class="item-hint">Thumbnail file missing on disk ({{ $thumbnail_path }}).</p>
        @elseif ($viewMode)
            <p class="item-hint">No thumbnail.</p>
        @endif
    </div>
</div>

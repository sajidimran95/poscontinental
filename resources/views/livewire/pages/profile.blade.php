<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app'), Title('My Profile')] class extends Component
{
    public string $name = '';

    public string $username = '';

    public ?string $avatar_path = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $statusMessage = '';

    public bool $avatarUploading = false;

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        $this->name = (string) $user->name;
        $this->username = (string) ($user->username ?? '');
        $this->avatar_path = filled($user->avatar_path) ? (string) $user->avatar_path : null;
    }

    public function with(): array
    {
        return [
            'avatarUrl' => $this->resolveAvatarUrl($this->avatar_path),
        ];
    }

    public function uploadAvatar(string $dataUrl, string $originalName = 'avatar.jpg'): void
    {
        $this->statusMessage = '';
        $this->resetErrorBag('avatar');

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        if (! preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/', $dataUrl, $matches)) {
            $this->addError('avatar', 'Invalid image data.');

            return;
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($binary === false || strlen($binary) < 24) {
            $this->addError('avatar', 'Could not read the image file.');

            return;
        }

        if (strlen($binary) > 2 * 1024 * 1024) {
            $this->addError('avatar', 'Image must be 2 MB or smaller.');

            return;
        }

        $mime = strtolower($matches[1]);
        $extMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
        ];
        $ext = $extMap[$mime] ?? strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: 'jpg');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (! in_array($ext, ['jpg', 'png', 'gif', 'webp', 'bmp'], true)) {
            $this->addError('avatar', 'Image must be JPG, PNG, GIF, WEBP, or BMP.');

            return;
        }

        Storage::disk('public')->makeDirectory('users/avatars');
        $path = 'users/avatars/'.$user->id.'_'.Str::uuid()->toString().'.'.$ext;
        Storage::disk('public')->put($path, $binary);
        $this->publishAvatar($path);

        if (! Storage::disk('public')->exists($path) && ! is_file(public_path('uploads/'.$path))) {
            $this->addError('avatar', 'Uploaded image was not saved on the server.');

            return;
        }

        $this->deleteAvatarFiles($user->avatar_path);
        $user->update(['avatar_path' => $path]);
        $this->avatar_path = $path;
        $this->statusMessage = 'Photo uploaded.';
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $this->deleteAvatarFiles($user->avatar_path);
        $user->update(['avatar_path' => null]);
        $this->avatar_path = null;
        $this->resetErrorBag('avatar');
        $this->statusMessage = 'Profile photo removed.';
    }

    public function save(): void
    {
        $this->statusMessage = '';
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $companyId = (int) $user->company_id;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:64',
                Rule::unique('users', 'username')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($user->id),
            ],
        ];

        $changingPassword = filled($this->password) || filled($this->password_confirmation) || filled($this->current_password);

        if ($changingPassword) {
            $rules['current_password'] = ['required', 'string', 'current_password'];
            $rules['password'] = ['required', 'string', Password::defaults(), 'confirmed'];
        }

        $validated = $this->validate($rules, [
            'username.required' => 'User ID is required.',
            'username.unique' => 'That User ID is already in use.',
            'current_password.required' => 'Enter your current password to change it.',
            'current_password.current_password' => 'Current password is incorrect.',
        ]);

        $data = [
            'name' => $validated['name'],
            'username' => $validated['username'],
        ];

        if ($changingPassword) {
            $data['password'] = $validated['password'];
        }

        $user->update($data);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->statusMessage = 'Profile saved.';
    }

    protected function resolveAvatarUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'users/avatars/')) {
            return null;
        }

        $published = public_path('uploads/'.$path);
        if (is_file($published)) {
            return '/uploads/'.$path.'?v='.(filemtime($published) ?: time());
        }

        if (Storage::disk('public')->exists($path)) {
            return '/media/'.$path.'?v='.Storage::disk('public')->lastModified($path);
        }

        return null;
    }

    protected function publishAvatar(string $path): void
    {
        $path = str_replace('\\', '/', ltrim($path, '/'));
        if (! Storage::disk('public')->exists($path)) {
            return;
        }

        $publicFull = public_path('uploads/'.$path);
        File::ensureDirectoryExists(dirname($publicFull));
        File::put($publicFull, Storage::disk('public')->get($path));
    }

    protected function deleteAvatarFiles(?string $path): void
    {
        if (! filled($path)) {
            return;
        }

        $path = str_replace('\\', '/', ltrim($path, '/'));
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'users/avatars/')) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $publicFull = public_path('uploads/'.$path);
        if (is_file($publicFull)) {
            @unlink($publicFull);
        }
    }
}; ?>

<div class="stamp-inv-page">
    <x-action-bar title="My Profile" />

    @if ($statusMessage !== '')
        <div class="stamp-inv-flash stamp-inv-flash-ok" role="status">{{ $statusMessage }}</div>
    @endif

    @if ($errors->any())
        <div class="stamp-inv-flash stamp-inv-flash-err" role="alert">
            <strong>Could not save:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="stamp-inv-body">
        <form wire:submit.prevent="save" class="stamp-inv-form profile-form" autocomplete="off">
            <h3 class="msa-section-title">Account</h3>

            <label class="stamp-inv-field">
                <span>User ID <em>*</em></span>
                <input type="text" wire:model="username" class="desk-input font-mono" autocomplete="username" />
            </label>

            <label class="stamp-inv-field">
                <span>Name <em>*</em></span>
                <input type="text" wire:model="name" class="desk-input" autocomplete="name" />
            </label>

            <h3 class="msa-section-title">Photo</h3>
            <div
                class="profile-photo-card"
                style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;padding:0.85rem;border:1px solid #e2e8f0;border-radius:0.5rem;background:#f8fafc;"
                x-data="{
                    uploading: false,
                    error: '',
                    pick() { this.$refs.file.click(); },
                    async upload(event) {
                        const input = event.target;
                        const file = input.files?.[0];
                        input.value = '';
                        if (!file) return;

                        const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/x-ms-bmp'];
                        if (file.type && !allowed.includes(file.type) && !/\.(jpe?g|png|gif|webp|bmp)$/i.test(file.name)) {
                            this.error = 'Image must be JPG, PNG, GIF, WEBP, or BMP.';
                            return;
                        }
                        if (file.size > 2 * 1024 * 1024) {
                            this.error = 'Image must be 2 MB or smaller.';
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
                            await $wire.uploadAvatar(dataUrl, file.name);
                        } catch (e) {
                            this.error = 'Upload failed. Try a smaller JPG/PNG file.';
                        } finally {
                            this.uploading = false;
                        }
                    }
                }"
            >
                <div
                    class="profile-photo-preview"
                    aria-hidden="true"
                    style="width:5.5rem;height:5.5rem;border-radius:0.65rem;overflow:hidden;border:1px solid #cbd5e1;background:#e2e8f0;display:grid;place-items:center;flex-shrink:0;"
                >
                    @if ($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="" style="width:100%;height:100%;object-fit:cover;" />
                    @else
                        <span style="font-size:1.75rem;font-weight:700;color:#64748b;line-height:1;">{{ strtoupper(substr($name !== '' ? $name : 'U', 0, 1)) }}</span>
                    @endif
                </div>

                <div style="display:flex;flex-direction:column;gap:0.45rem;align-items:flex-start;min-width:12rem;">
                    <input
                        type="file"
                        x-ref="file"
                        accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,image/*"
                        style="display:none;"
                        x-on:change="upload($event)"
                    />
                    <div style="display:flex;flex-wrap:wrap;gap:0.4rem;">
                        <button type="button" class="desk-btn desk-btn-primary" x-on:click="pick()" x-bind:disabled="uploading">
                            <span x-show="!uploading">{{ $avatarUrl ? 'Change photo' : 'Upload photo' }}</span>
                            <span x-show="uploading" x-cloak>Uploading…</span>
                        </button>
                        @if ($avatarUrl)
                            <button type="button" wire:click="removeAvatar" class="desk-btn" x-bind:disabled="uploading">Remove</button>
                        @endif
                    </div>
                    <p class="stamp-inv-hint" style="color:#b91c1c;margin:0;" x-show="error" x-text="error" x-cloak></p>
                    @error('avatar')
                        <p class="stamp-inv-hint" style="color:#b91c1c;margin:0;">{{ $message }}</p>
                    @enderror
                    <p class="stamp-inv-hint" style="margin:0;">JPG, PNG, GIF, or WEBP — max 2 MB.</p>
                </div>
            </div>

            <h3 class="msa-section-title">Password</h3>
            <p class="stamp-inv-hint">Leave blank to keep your current password.</p>
            <label class="stamp-inv-field">
                <span>Current password</span>
                <input type="password" wire:model="current_password" class="desk-input" autocomplete="current-password" />
            </label>
            <div class="msa-field-grid">
                <label class="stamp-inv-field">
                    <span>New password</span>
                    <input type="password" wire:model="password" class="desk-input" autocomplete="new-password" />
                </label>
                <label class="stamp-inv-field">
                    <span>Confirm password</span>
                    <input type="password" wire:model="password_confirmation" class="desk-input" autocomplete="new-password" />
                </label>
            </div>

            <div class="stamp-inv-actions">
                <button type="submit" class="desk-btn desk-btn-primary" wire:loading.attr="disabled">Save Profile</button>
                <a href="{{ route('home') }}" wire:navigate class="desk-btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

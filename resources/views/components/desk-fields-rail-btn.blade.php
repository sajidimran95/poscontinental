<button type="button" class="desk-rail-btn" title="Show/Hide Fields" aria-label="Show/Hide Fields" @click="$dispatch('{{ $event ?? 'open-list-fields' }}')">
    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.35" aria-hidden="true">
        <rect x="1.5" y="2.5" width="13" height="11" rx="1"/>
        <path d="M1.5 6h13M6 2.5v11"/>
        <rect x="9.2" y="8.6" width="5.2" height="5.2" rx="0.6" fill="#fff" stroke="currentColor" stroke-width="1.2"/>
    </svg>
</button>

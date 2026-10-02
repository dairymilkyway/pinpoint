@can('addresses.edit')
    <a href="{{ route('addresses.edit', $address) }}" class="btn-ghost" title="Edit this address">
        <i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span>
    </a>
@endcan

@can('addresses.delete')
    <button type="button"
            class="btn-ghost btn-ghost--danger"
            title="Delete this address"
            data-bs-toggle="modal"
            data-bs-target="#deleteAddressModal"
            data-delete-action="{{ route('addresses.destroy', $address) }}"
            data-delete-label="{{ $address->label }}">
        <i class="bi bi-trash"></i><span class="visually-hidden">Delete</span>
    </button>
@endcan

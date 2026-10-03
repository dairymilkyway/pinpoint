{{-- Two shapes of the same row.

     A reader edits and deletes outright. A Customer has neither permission, so
     instead of an empty cell they get the three things they can actually do:
     move their own default marker, and ask for an edit or a deletion. The
     permission decides which shape renders, so there is no role check here. --}}

@can('addresses.edit')
    <a href="{{ route('addresses.edit', $address) }}" class="btn-ghost" title="Edit this address">
        <i class="bi bi-pencil"></i><span class="visually-hidden">Edit</span>
    </a>
@elsecan('addresses.request')
    <a href="{{ route('requests.create', ['address' => $address->id]) }}" class="btn-ghost"
       title="Ask an administrator to edit this address">
        <i class="bi bi-pencil-square"></i><span class="visually-hidden">Request edit</span>
    </a>
@endcan

@can('addresses.delete')
    <button type="button"
            class="btn-ghost btn-ghost--danger"
            title="Delete this address"
            data-bs-toggle="modal"
            data-bs-target="#deleteAddressModal"
            data-row-action="{{ route('addresses.destroy', $address) }}"
            data-row-label="{{ $address->label }}">
        <i class="bi bi-trash"></i><span class="visually-hidden">Delete</span>
    </button>
@elsecan('addresses.request')
    <button type="button"
            class="btn-ghost"
            title="Ask an administrator to delete this address"
            data-bs-toggle="modal"
            data-bs-target="#requestDeleteModal"
            data-row-action="{{ route('requests.store') }}"
            data-row-value="{{ $address->id }}"
            data-row-label="{{ $address->label }}">
        <i class="bi bi-trash3"></i><span class="visually-hidden">Request deletion</span>
    </button>
@endcan

@can('setDefault', $address)
    @unless ($address->is_default)
        <form method="POST" action="{{ route('addresses.default', $address) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn-ghost" title="Make this the default address">
                <i class="bi bi-star"></i><span class="visually-hidden">Make default</span>
            </button>
        </form>
    @endunless
@endcan

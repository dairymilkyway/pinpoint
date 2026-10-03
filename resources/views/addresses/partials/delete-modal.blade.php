{{-- One modal per destructive kind, re-pointed at whichever row was clicked.

     The modal declares which elements inside it get filled; the button carries
     the values. That way a second modal costs no JavaScript, which is what the
     request-a-deletion modal below relies on. --}}
<div class="modal fade" id="deleteAddressModal" tabindex="-1" aria-labelledby="deleteAddressModalLabel" aria-hidden="true"
     data-row-modal data-form-target="#deleteAddressForm" data-label-target="#deleteAddressLabel">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="deleteAddressForm">
            @csrf
            @method('DELETE')

            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1">Confirm</p>
                        <h5 class="modal-title" id="deleteAddressModalLabel">Delete address</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-0">
                        Delete <strong id="deleteAddressLabel" class="text-white"></strong>?
                        This cannot be undone.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash"></i> <span class="ms-1">Delete</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@can('addresses.request')
    <div class="modal fade" id="requestDeleteModal" tabindex="-1" aria-labelledby="requestDeleteModalLabel" aria-hidden="true"
         data-row-modal data-form-target="#requestDeleteForm" data-label-target="#requestDeleteLabel"
         data-value-target="#requestDeleteAddress">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="{{ route('requests.store') }}" id="requestDeleteForm">
                @csrf
                <input type="hidden" name="type" value="delete">
                <input type="hidden" name="address_id" id="requestDeleteAddress" value="">

                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <p class="eyebrow mb-1">Request</p>
                            <h5 class="modal-title" id="requestDeleteModalLabel">Request deletion</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <p class="mb-0">
                            Ask an administrator to delete
                            <strong id="requestDeleteLabel" class="text-white"></strong>?
                            The address stays in the book until the request is approved.
                        </p>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> <span class="ms-1">Send request</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endcan

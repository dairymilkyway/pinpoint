<div class="modal fade" id="deleteAddressModal" tabindex="-1" aria-labelledby="deleteAddressModalLabel" aria-hidden="true">
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

{{-- Confirmation for the two role controls, re-pointed at whichever row was
     clicked. Same mechanism as the address delete modal: the modal declares the
     elements that get filled and the row button carries the values, so a second
     dialog costs no JavaScript. Both sit at page level, never inside a row form.

     Reactivate is deliberately left unconfirmed - it is the undo direction. --}}
<div class="modal fade" id="rbacRoleModal" tabindex="-1" aria-labelledby="rbacRoleModalLabel" aria-hidden="true"
     data-row-modal data-form-target="#rbacRoleForm" data-label-target="#rbacRoleLabel"
     data-value-target="#rbacRoleValue" data-text-target="#rbacRoleName">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="rbacRoleForm">
            @csrf
            <input type="hidden" name="role" id="rbacRoleValue" value="">

            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1">Confirm</p>
                        <h5 class="modal-title" id="rbacRoleModalLabel">Change role</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-0">
                        Change the role for <strong id="rbacRoleLabel" class="text-body"></strong>
                        to <strong id="rbacRoleName" class="text-body"></strong>?
                    </p>
                    <p class="mb-0 text-dim small mt-2">
                        The new role applies immediately.
                    </p>
                </div>

                <div class="modal-footer action-bar">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-shield-check"></i> <span class="ms-1">Update role</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="rbacDeactivateModal" tabindex="-1" aria-labelledby="rbacDeactivateModalLabel" aria-hidden="true"
     data-row-modal data-form-target="#rbacDeactivateForm" data-label-target="#rbacDeactivateLabel">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="rbacDeactivateForm">
            @csrf

            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1">Confirm</p>
                        <h5 class="modal-title" id="rbacDeactivateModalLabel">Deactivate account</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-0">
                        Deactivate <strong id="rbacDeactivateLabel" class="text-body"></strong>?
                    </p>
                    <p class="mb-0 text-dim small mt-2">
                        They can no longer sign in. Their addresses stay in the directory.
                    </p>
                </div>

                <div class="modal-footer action-bar">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-person-slash"></i> <span class="ms-1">Deactivate</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

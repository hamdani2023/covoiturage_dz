</div><!-- End of main-content -->
</div><!-- End of wrapper -->

<!-- jQuery, Bootstrap JS, and other plugins -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.fr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Custom Admin JS -->
<script>
    $(document).ready(function() {
        // Initialize DataTables
        $('.datatable').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/fr-FR.json'
            },
            responsive: true
        });

        // Initialize datepicker
        $('.datepicker').datepicker({
            format: 'dd/mm/yyyy',
            autoclose: true,
            todayHighlight: true,
            language: 'fr'
        });

        // Toggle sidebar on mobile
        $('.sidebar-toggle').click(function() {
            $('.sidebar').toggleClass('collapsed');
            $('.main-content').toggleClass('expanded');
        });

        // Confirm before destructive actions
        $('.confirm-action').click(function() {
            return confirm($(this).data('confirm') || 'Êtes-vous sûr de vouloir effectuer cette action?');
        });

        // Tooltip initialization
        $('[data-toggle="tooltip"]').tooltip();

        // Handle active nav items
        $('.sidebar-menu li a').each(function() {
            if ($(this).attr('href') === window.location.pathname) {
                $(this).parent().addClass('active');
            }
        });
    });

    // Function to show toast notifications
    function showToast(type, message) {
        const toast = $(`<div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>`);

        $('#toast-container').append(toast);
        new bootstrap.Toast(toast[0]).show();

        setTimeout(() => {
            toast.remove();
        }, 5000);
    }

    // Display any flash messages
    <?php if (isset($_SESSION['flash_message'])): ?>
        showToast('<?= $_SESSION['flash_message']['type'] ?>', '<?= $_SESSION['flash_message']['text'] ?>');
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>
</script>

<!-- Toast container -->
<div id="toast-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100"></div>
</body>

</html>
  <footer class="main-footer admin-footer">
    <strong>Blockrium Admin</strong>
    <span class="d-none d-sm-inline">Operations dashboard and content control panel.</span>
    <div class="float-right d-none d-sm-inline-block">
      <span>{{ now()->format('d M Y, h:i A') }}</span>
    </div>
  </footer>

  <aside class="control-sidebar control-sidebar-dark"></aside>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-ui-dist@1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.30.1/min/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs4.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script>
  $(function () {
    $('.summernote').summernote({
      height: 220,
      toolbar: [
        ['style', ['bold', 'italic', 'underline', 'clear']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['insert', ['link']],
        ['view', ['codeview']]
      ]
    });

    setTimeout(function () {
      $('.alert').fadeOut(200);
    }, 3500);

    $('[data-admin-nav-search]').on('input', function () {
      var query = $(this).val().toLowerCase().trim();
      $('[data-admin-nav-item]').each(function () {
        var item = $(this);
        var text = item.text().toLowerCase();
        item.toggle(!query || text.indexOf(query) !== -1);
      });
    });

    $('a[href*="delete_"], a[href*="delete-"], a[data-confirm]').on('click', function (event) {
      var message = $(this).data('confirm') || 'Are you sure you want to delete this item?';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
</script>
</body>
</html>

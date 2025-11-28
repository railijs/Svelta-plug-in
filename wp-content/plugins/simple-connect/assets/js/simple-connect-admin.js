(function ($) {
  $(document).ready(function () {
    var $btn = $(".sc-disconnect-btn-js");
    if (!$btn.length) {
      return;
    }

    $btn.on("click", function (e) {
      e.preventDefault();

      var btn = $(this);
      if (!confirm("Disconnect this store from Svelta?")) {
        return;
      }

      btn.prop("disabled", true).text("Disconnecting...");

      $.post(
        SimpleConnectAdmin.ajaxUrl,
        {
          action: "svelta_disconnect",
          nonce: SimpleConnectAdmin.disconnectNonce,
        },
        function (resp) {
          if (resp && resp.success) {
            location.reload();
          } else {
            alert("Failed to disconnect from Svelta.");
            btn.prop("disabled", false).text("Disconnect from Svelta");
          }
        }
      ).fail(function () {
        alert("Network error. Please try again.");
        btn.prop("disabled", false).text("Disconnect from Svelta");
      });
    });
  });
})(jQuery);

(function ($) {
  $(document).ready(function () {
    // 1) Move the wrapper into the right sidebar on the order edit screen.
    var $wrap = $("#svelta-order-card-wrapper");
    if ($wrap.length) {
      var $sidebarSortables = $("#postbox-container-1 .meta-box-sortables");
      if ($sidebarSortables.length) {
        var $orderActions = $("#woocommerce-order-actions");
        if ($orderActions.length) {
          $orderActions.after($wrap);
        } else {
          $sidebarSortables.prepend($wrap);
        }
      }
    }

    // 2) Wire up each Svelta order card.
    $(".svelta-order-card").each(function () {
      var $card = $(this);
      var orderId = $card.data("order-id");
      var nonce = $card.data("nonce");

      // If there's no order id / nonce (e.g. "not connected" message), skip JS logic.
      if (!orderId || !nonce) {
        return;
      }

      var $status = $card.find(".svelta-dr-status-text");
      var $statusLine = $card.find(".svelta-status-line");
      var $pill = $card.find(".svelta-dr-id-pill");
      var $msgMain = $card.find(".svelta-message-main");
      var $msgError = $card.find(".svelta-message-error");
      var $btnCreate = $card.find(".svelta-create-btn");
      var $btnCancel = $card.find(".svelta-cancel-btn");

      function setLoading(message) {
        $msgMain.text(message).show();
        $msgError.hide().text("");
        $btnCreate.prop("disabled", true);
        $btnCancel.prop("disabled", true);
      }

      function clearLoading() {
        $btnCreate.prop("disabled", false);
        $btnCancel.prop("disabled", false);
      }

      function showError(message) {
        $msgError.text(message).show();
      }

      function updateUIHasDR(drId) {
        $status.text("Created");
        $pill.text("DR ID: " + drId);
        $statusLine.show();
        $btnCreate.hide();
        $btnCancel.show();
        $msgMain.text("This order already has a delivery request on Svelta.");
      }

      function updateUINoDR() {
        $status.text("Not created yet");
        $statusLine.hide();
        $btnCancel.hide();
        $btnCreate.show();
        $msgMain.text(
          "You can manually create a delivery request for this order."
        );
      }

      // Initial status check: ask Svelta if a delivery request exists.
      setLoading("Checking Svelta delivery status…");
      $.post(
        ajaxurl,
        {
          action: "svelta_order_status",
          nonce: nonce,
          order_id: orderId,
        },
        function (resp) {
          clearLoading();
          if (!resp || !resp.success) {
            showError(
              resp && resp.data && resp.data.message
                ? resp.data.message
                : "Could not check delivery status on Svelta."
            );
            $status.text("Unknown");
            return;
          }

          if (resp.data && resp.data.has_dr && resp.data.dr_id) {
            updateUIHasDR(resp.data.dr_id);
          } else {
            updateUINoDR();
          }
        }
      ).fail(function () {
        clearLoading();
        showError("Network error while talking to Svelta.");
        $status.text("Unknown");
      });

      // Create button: manually create a delivery request.
      $btnCreate.on("click", function (e) {
        e.preventDefault();
        if (!confirm("Create a delivery request on Svelta for this order?"))
          return;

        setLoading("Creating delivery request on Svelta…");
        $.post(
          ajaxurl,
          {
            action: "svelta_order_create",
            nonce: nonce,
            order_id: orderId,
          },
          function (resp) {
            clearLoading();
            if (!resp || !resp.success) {
              showError(
                resp && resp.data && resp.data.message
                  ? resp.data.message
                  : "Could not create delivery request on Svelta."
              );
              return;
            }

            if (resp.data && resp.data.dr_id) {
              updateUIHasDR(resp.data.dr_id);
            } else {
              showError("Svelta did not return a delivery ID.");
            }
          }
        ).fail(function () {
          clearLoading();
          showError("Network error while talking to Svelta.");
        });
      });

      // Cancel button: cancel an existing delivery request.
      $btnCancel.on("click", function (e) {
        e.preventDefault();
        if (
          !confirm(
            "Cancel the existing delivery request on Svelta for this order?"
          )
        )
          return;

        setLoading("Cancelling delivery request on Svelta…");
        $.post(
          ajaxurl,
          {
            action: "svelta_order_cancel",
            nonce: nonce,
            order_id: orderId,
          },
          function (resp) {
            clearLoading();
            if (!resp || !resp.success) {
              showError(
                resp && resp.data && resp.data.message
                  ? resp.data.message
                  : "Could not cancel delivery request on Svelta."
              );
              return;
            }

            updateUINoDR();
          }
        ).fail(function () {
          clearLoading();
          showError("Network error while talking to Svelta.");
        });
      });
    });
  });
})(jQuery);

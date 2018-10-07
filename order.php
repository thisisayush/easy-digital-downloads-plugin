<div ng-app="shopping-cart-demo">
  <div ng-controller="CheckoutController">

    <div class="bnomics-order-container" style="max-width: 600px;">
      <!-- Heading row -->
      <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
          <div class="bnomics-order-id">
            <span class="bnomics-order-number" ng-cloak> <?=__('Order#', 'edd-blockonomics')?> {{order.order_id}}</span>
            <span class="alignright ng-cloak bnomics-time-left" ng-hide="order.status != -1">{{clock*1000 | date:'mm:ss' : 'UTC'}}</span>
          </div>

          <div ng-cloak ng-hide="order.status != -1" class="bnomics-progress-bar-wrapper">
            <div class="bnomics-progress-bar-container">
              <div class="bnomics-progress-bar" style="width: {{progress}}%;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Amount row -->
      <div class="bnomics-order-panel">
        <div class="bnomics-order-info">

          <div class="bnomics-bitcoin-pane">
            <!-- Order Status -->
            <h4 class="bnomics-order-status-title" ng-show="order.status != -1" for="invoice-amount" style="margin-top:15px;" ng-cloak><?=__('Status', 'edd-blockonomics')?></h4>
            <div class="bnomics-order-status-wrapper">
              <h4 class="bnomics-order-status-title" ng-show="order.status == -1" ng-cloak ><?=__('To pay, send exact amount of BTC to the given address', 'edd-blockonomics')?></h4>
              <span class="warning bnomics-status-warning" ng-show="order.status == -3" ng-cloak><?=__('Payment Expired (Use browser back button and try again)', 'edd-blockonomics')?></span>
              <span class="warning bnomics-status-warning" ng-show="order.status == -2" ng-cloak><?=__('Payment Error', 'edd-blockonomics')?></span>
              <span ng-show="order.status == 0" ng-cloak><?=__('Unconfirmed', 'edd-blockonomics')?></span>
              <span ng-show="order.status == 1" ng-cloak><?=__('Partially Confirmed', 'edd-blockonomics')?></span>
              <span ng-show="order.status >= 2" ng-cloak ><?=__('Confirmed', 'edd-blockonomics')?></span>
            </div>

            <div class="bnomics-btc-info">
              <!-- QR and Amount -->
              <div class="bnomics-qr-code">
                <h5 class="bnomics-qr-code-title" for="btn-address"><?=__('Bitcoin Address', 'edd-blockonomics')?></h5>
                <a href="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}">
                  <qrcode data="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}" size="160" version="6">
                    <canvas class="qrcode"></canvas>
                  </qrcode>
                </a>
                <h5 class="bnomics-qr-code-hint"><?=__('Click on the QR code above to open in wallet', 'edd-blockonomics')?></h5>
              </div>

              <!-- BTC Amount -->
              <div class="bnomics-amount">
                <h4 class="bnomics-amount-title" for="invoice-amount"><?=__('Amount', 'edd-blockonomics')?></h4>
                <div class="bnomics-amount-wrapper">
                  <span ng-show="order.satoshi" ng-cloak>{{order.satoshi/1.0e8}}</span>
                  <small>BTC</small> ⇌
                  <span ng-cloak>{{order.value}}</span>
                  <small ng-cloak>{{order.currency}}</small>
                </div>
              </div>
            </div>

            <!-- Bitcoin Address -->
            <div class="bnomics-address">
              <input class="bnomics-address-input" type="text" ng-value="order.address" readonly="readonly">
            </div>
            <div class="bnomics-powered-by">
              <?=__('Powered by ', 'edd-blockonomics')?>Blockonomics
            </div>
          </div>
        </div>
      </div>
    </div>
    <script>
    var blockonomics_time_period=<?php echo edd_get_option('edd_blockonomics_payment_countdown_time', 10); ?>;
    </script>
    <script src="<?php echo plugins_url('js/angular.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/angular-resource.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/app.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/angular-qrcode.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/vendors.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/reconnecting-websocket.min.js', __FILE__);?>"></script>
  </div>
</div>

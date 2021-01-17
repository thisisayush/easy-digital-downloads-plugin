<?php get_header();?>
<div ng-app="shopping-cart-demo">
  <div ng-controller="CheckoutController">
    <div class="bnomics-order-container">
      <!-- Heading row -->
      <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
          <div class="bnomics-order-id">
            <span class="bnomics-order-number" ng-cloak> <?=__('Order #', 'edd-blockonomics')?>{{order.order_id}}</span>
          </div>
        </div>
      </div>
      <!-- Amount row -->
      <div class="bnomics-order-panel">
        <div class="bnomics-order-info">

          <div class="bnomics-bitcoin-pane" ng-hide="show_altcoin != 0" ng-init="show_altcoin=0">
            <div class="bnomics-btc-info">
              <!-- QR and Amount -->
              <div class="bnomics-qr-code" ng-hide="order.status == -3">
                <div class="bnomics-qr">
                          <a href="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}">
                            <qrcode data="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}" size="160" version="6">
                              <canvas class="qrcode"></canvas>
                            </qrcode>
                          </a>
                </div>
                <div class="bnomics-qr-code-hint">
                  <a href="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}" target="_blank"><?=__('Open in wallet', 'edd-blockonomics')?></a>
                </div>
              </div>
              <!-- BTC Amount -->
              <div class="bnomics-amount">
              <div class="bnomics-bg">
                <!-- Order Status -->
                <div class="bnomics-order-status-wrapper">
                  <span class="bnomics-order-status-title" ng-show="order.status == -1" ng-cloak ><?=__('To pay, send exactly this BTC {{altcoinselect}} amount')?></span>
                  <span class="warning bnomics-status-warning" ng-show="order.status == -3" ng-cloak><?=__('Payment Expired (Use the browser back button and try again)', 'edd-blockonomics')?></span>
                  <span class="warning bnomics-status-warning" ng-show="order.status == -2" ng-cloak><?=__('Payment Error', 'edd-blockonomics')?></span>
                </div>
                    <div>
                    <ul ng-click="blockonomics_amount_click()" id="bnomics-amount-input" class="bnomics-amount-input">
                        <li id="bnomics-amount-copy">{{order.satoshi/1.0e8}} BTC</li>
                        <li>{{crypto.code | uppercase}}</li>
                        <li class="bnomics-grey"> ≈ </li>
                        <li class="bnomics-grey">{{order.value}}</li>
                        <li class="bnomics-grey">{{order.currency}}</li>
                    </ul>
                    </div>
              <!-- Bitcoin Address -->
              <div class="bnomics-address">
                    <div class="bnomics-address-text" ng-hide="address_copyshow" ng-cloak><?=__('To this bitcoin{{crypto.name | lowercase}} address', 'edd-blockonomics')?></div>
                    <div class="bnomics-copy-address-text" ng-show="address_copyshow" ng-cloak><?=__('Copied to clipboard', 'edd-blockonomics')?></div>
                    <ul ng-click="blockonomics_address_click()" id="bnomics-address-input" class="bnomics-address-input">
                          <li id="bnomics-address-copy">{{order.address}}</li>
                    </ul>
                  </div>
            <!-- Countdown Timer -->
                <div ng-cloak ng-hide="order.status != -1" class="bnomics-progress-bar-wrapper">
                  <div class="bnomics-progress-bar-container">
                    <div class="bnomics-progress-bar" style="width: {{progress}}%;"></div>
                  </div>
                </div>
                <span class="ng-cloak bnomics-time-left" ng-hide="order.status != -1">{{clock*1000 | date:'mm:ss' : 'UTC'}} min</span>
              </div>
        <!-- Blockonomics Credit -->
                <div class="bnomics-powered-by">
                  <a href="https://blog.blockonomics.co/how-to-pay-a-bitcoin-invoice-abf4a04d041c" target="_blank"><?=__('How do I pay? | Check reviews of this shop', 'blockonomics-bitcoin-payments')?></a><br>
                  <div class="bnomics-powered-by-text bnomics-grey"><?=__('Powered by Blockonomics', 'edd-blockonomics')?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script>
    var blockonomics_time_period=<?php echo edd_get_option('edd_blockonomics_payment_countdown_time', 10); ?>;
    </script>
    <script>
    var get_uuid="<?php if(isset($_REQUEST['uuid'])){echo $_REQUEST['uuid'];} ?>";
    </script>
  </div>
</div>

<?php get_footer();?>
service = angular.module("shoppingcart.services", ["ngResource"]);

service.factory('Order', function($resource) {
    //There are two styles of callback url in 
    //woocommerce, we have to support both
    //https://docs.woocommerce.com/document/wc_api-the-woocommerce-api-callback/
    var param = getParameterByNameBlocko('edd-listener');
    if (param)
        param = {
            "edd-listener": param
        };
    else
        param = {};
    var item = $resource(window.location.pathname, param);
    return item;
});

app = angular.module("shopping-cart-demo", ["monospaced.qrcode", "shoppingcart.services"]);


app.config(function($compileProvider) {
    $compileProvider.aHrefSanitizationWhitelist(/^\s*(https?|ftp|mailto|data|chrome-extension|bitcoin):/);
    // Angular before v1.2 uses $compileProvider.urlSanitizationWhitelist(...)
});

function getParameterByNameBlocko(name, url) {
    if (!url) {
        url = window.location.href;
    }
    name = name.replace(/[\[\]]/g, "\\$&");
    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, " "));
}

//CheckoutController
app.controller('CheckoutController', function($scope, $interval, Order, $httpParamSerializer, $timeout) {
    //get order id from url
    $scope.address = getParameterByNameBlocko("show_order");
    var totalProgress = 100;
    $scope.copyshow = false;
    //blockonomics_time_period is defined on JS file as global var
    var totalTime = blockonomics_time_period * 60;

    //Create url when the order is received 
    $scope.finish_order_url = function() {
        var params = getParameterByNameBlocko('edd-listener');
        if (params)
            params = {
                "edd-listener": params
            };
        else
            params = {};
        params.finish_order = $scope.address;
        url = window.location.pathname;
        var serializedParams = $httpParamSerializer(params);
        if (serializedParams.length > 0) {
            url += ((url.indexOf('?') === -1) ? '?' : '&') + serializedParams;
        }
        return url;
    }

    //Increment bitcoin timer 
    $scope.tick = function() {
        $scope.clock = $scope.clock - 1;
        $scope.progress = Math.floor($scope.clock * totalProgress / totalTime);
        if ($scope.clock < 0) {
            $scope.clock = 0;
            //Order expired
            $scope.order.status = -3;
        }
        $scope.progress = Math.floor($scope.clock * totalProgress / totalTime);
    };


    //Check if the bitcoin address is present
    if (typeof $scope.address != 'undefined') {
        //Fetch the order using address
        Order.get({
            "get_order": $scope.address
        }, function(data) {
            $scope.order = data;
            $scope.order.address = $scope.address;
            //Check the status of the order
            if ($scope.order.status == -1) {
                $scope.clock = $scope.order.time_remaining;
                //Mark order as expired if we ran out of time
                if ($scope.clock < 0) {
                    $scope.order.status = -3;
                    return;
                }
                $scope.tick_interval = $interval($scope.tick, 1000);
                //Connect and Listen on websocket for payment notification
                var ws = new ReconnectingWebSocket("wss://www.blockonomics.co/payment/" + $scope.order.address);
                ws.onmessage = function(evt) {
                    ws.close();
                    $interval(function() {
                        //Redirect to order received page if message from socket
                        window.location = $scope.finish_order_url();
                    //Wait for 2 seconds for order status to update on server
                    }, 2000, 1);
                }
            }
        });
    }

    function select_text(divid)
    {
        var selection = window.getSelection();
        var div = document.createRange();

        div.setStartBefore(document.getElementById(divid));
        div.setEndAfter(document.getElementById(divid)) ;
        selection.removeAllRanges();
        selection.addRange(div);
    }

    function copy_to_clipboard(divid)
    {
        var textarea = document.createElement('textarea');
        textarea.id = 'temp_element';
        textarea.style.height = 0;
        document.body.appendChild(textarea);
        textarea.value = document.getElementById(divid).innerText;
        var selector = document.querySelector('#temp_element');
        selector.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        select_text(divid);

        if (divid == "bnomics-address-copy") {
            $scope.address_copyshow = true;
            $timeout(function() {
                $scope.address_copyshow = false;
                //Close copy to clipboard message after 2 sec
            }, 2000);
        }else{
            $scope.amount_copyshow = true;
            $timeout(function() {
                $scope.amount_copyshow = false;
                //Close copy to clipboard message after 2 sec
            }, 2000);            
        }
    }

    //Copy bitcoin address to clipboard
    $scope.blockonomics_address_click = function() {
        copy_to_clipboard("bnomics-address-copy");
    }

    //Copy bitcoin amount to clipboard
    $scope.blockonomics_amount_click = function() {
        copy_to_clipboard("bnomics-amount-copy");
    }

});

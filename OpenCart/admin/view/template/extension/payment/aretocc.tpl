<?php echo $header; ?><?php echo $column_left; ?>
    <div id="content">
        <div class="page-header">
            <div class="container-fluid">
                <h1>
                    <img src="view/image/payment/aretocc.png" alt=""/> <?php echo $heading_title; ?>
                </h1>
                <ul class="breadcrumb">
                    <?php foreach ($breadcrumbs as $breadcrumb): ?>
                    <li>
                        <a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a>
                        <?php endforeach; ?>
                    </li>
                </ul>
                <div class="pull-right">
                    <a onclick="$('#form').submit();" class="btn btn-primary"><?php echo $button_save; ?></a>
                    <a onclick="location = '<?php echo $cancel; ?>';" class="btn btn-default"><?php echo $button_cancel; ?></a>
                </div>
            </div>
        </div>
        <div class="container-fluid">
        </div>
        <?php foreach ($error as $item) : ?>
            <div class="warning">
                <?php echo $item ?>
            </div>
        <?php endforeach; ?>
        <div class="panel panel-default">
            <div class="panel-body">
                <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form">
                    <ul id="tabs" class="nav nav-tabs">
                        <li class="active"><a href="#tab-settings" data-toggle="tab"><?php echo $text_settings; ?></a>
                        </li>
                        <li>
                            <?php if (count($orders) > 0): ?>
                                <a href="#tab-orders" data-toggle="tab"><?php echo $text_orders; ?></a>
                            <?php endif; ?>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab-settings">
                            <table class="table table-bordered table-hover">
                                <tr>
                                    <td>
                                        <label for="aretocc_api_id"><?php echo $text_api_id; ?></label>
                                    </td>
                                    <td>
                                        <input type="text" name="aretocc_api_id" id="aretocc_api_id"
                                               value="<?php echo $aretocc_api_id; ?>" autocomplete="off">
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <label for="api_session"><?php echo $text_api_session; ?></label>
                                    </td>
                                    <td>
                                        <input type="password" name="aretocc_api_session" id="aretocc_api_session"
                                               value="<?php echo $aretocc_api_session; ?>" autocomplete="off">
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php echo $text_total; ?></td>
                                    <td><input type="text" name="aretocc_total" value="<?php echo $aretocc_total; ?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php echo $text_complete_status; ?></td>
                                    <td><select name="aretocc_completed_status_id">
                                            <?php foreach ($order_statuses as $order_status): ?>
                                                <?php if ($order_status['order_status_id'] == $aretocc_completed_status_id): ?>
                                                    <option value="<?php echo $order_status['order_status_id']; ?>"
                                                            selected="selected"><?php echo $order_status['name']; ?></option>
                                                <?php else: ?>
                                                    <option
                                                        value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select></td>
                                </tr>
                                <tr>
                                    <td><?php echo $text_pending_status; ?></td>
                                    <td><select name="aretocc_pending_status_id">
                                            <?php foreach ($order_statuses as $order_status): ?>
                                                <?php if ($order_status['order_status_id'] == $aretocc_pending_status_id): ?>
                                                    <option value="<?php echo $order_status['order_status_id']; ?>"
                                                            selected="selected"><?php echo $order_status['name']; ?></option>
                                                <?php else: ?>
                                                    <option
                                                        value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select></td>
                                </tr>
                                <tr>
                                    <td><?php echo $text_canceled_status; ?></td>
                                    <td><select name="aretocc_canceled_status_id">
                                            <?php foreach ($order_statuses as $order_status): ?>
                                                <?php if ($order_status['order_status_id'] == $aretocc_canceled_status_id): ?>
                                                    <option value="<?php echo $order_status['order_status_id']; ?>"
                                                            selected="selected"><?php echo $order_status['name']; ?></option>
                                                <?php else: ?>
                                                    <option
                                                        value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select></td>
                                </tr>
                                <tr>
                                    <td><?php echo $text_failed_status; ?></td>
                                    <td><select name="aretocc_failed_status_id">
                                            <?php foreach ($order_statuses as $order_status): ?>
                                                <?php if ($order_status['order_status_id'] == $aretocc_failed_status_id): ?>
                                                    <option value="<?php echo $order_status['order_status_id']; ?>"
                                                            selected="selected"><?php echo $order_status['name']; ?></option>
                                                <?php else: ?>
                                                    <option
                                                        value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select></td>
                                </tr>
                                <tr>
                                    <td><?php echo $text_refunded_status; ?></td>
                                    <td><select name="aretocc_refunded_status_id">
                                            <?php foreach ($order_statuses as $order_status): ?>
                                                <?php if ($order_status['order_status_id'] == $aretocc_refunded_status_id): ?>
                                                    <option value="<?php echo $order_status['order_status_id']; ?>"
                                                            selected="selected"><?php echo $order_status['name']; ?></option>
                                                <?php else: ?>
                                                    <option
                                                        value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select></td>
                                </tr>
                                <tr>
                                    <td><?php echo $text_geo_zone; ?></td>
                                    <td><select name="aretocc_geo_zone_id">
                                            <option value="0"><?php echo $text_all_zones; ?></option>
                                            <?php foreach ($geo_zones as $geo_zone): ?>
                                                <?php if ($geo_zone['geo_zone_id'] == $aretocc_geo_zone_id): ?>
                                                    <option value="<?php echo $geo_zone['geo_zone_id']; ?>"
                                                            selected="selected"><?php echo $geo_zone['name']; ?></option>
                                                <?php else: ?>
                                                    <option
                                                        value="<?php echo $geo_zone['geo_zone_id']; ?>"><?php echo $geo_zone['name']; ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select></td>
                                </tr>
                                <tr>
                                    <td><?php echo $text_status; ?></td>
                                    <td><select name="aretocc_status">
                                            <?php if ($aretocc_status): ?>
                                                <option value="1"
                                                        selected="selected"><?php echo $text_enabled; ?></option>
                                                <option value="0"><?php echo $text_disabled; ?></option>
                                            <?php else: ?>
                                                <option value="1"><?php echo $text_enabled; ?></option>
                                                <option value="0"
                                                        selected="selected"><?php echo $text_disabled; ?></option>
                                            <?php endif; ?>
                                        </select></td>
                                </tr>
                                <tr>
                                    <td><?php echo $text_sort_order; ?></td>
                                    <td><input type="text" name="aretocc_sort_order"
                                               value="<?php echo $aretocc_sort_order; ?>"
                                               size="1"/></td>
                                </tr>
                            </table>
                        </div>

                        <?php if (count($orders) > 0): ?>
                            <div class="tab-pane" id="tab-orders">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                    <tr>
                                        <th><?php echo $text_order_id; ?></th>
                                        <th><?php echo $text_internal_order_id; ?></th>
                                        <th><?php echo $text_actions; ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr style="text-align: center;">
                                            <td>
                                                <a href="<?php echo $order['order_link']; ?>"><?php echo $order['order_id']; ?>
                                            </td>
                                            <td><?php echo $order['internal_order_id']; ?></td>
                                            <td>
                                                <input type="button" class="refund_button" name="refund_button"
                                                       value="<?php echo $text_refund; ?>"
                                                       data-total="<?php echo $order['total']; ?>"
                                                       data-refunded="<?php echo $order['total_refunded']; ?>"
                                                       data-order-id="<?php echo $order['order_id']; ?>"
                                                       data-internal-order-id="<?php echo $order['internal_order_id']; ?>"
                                                >
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>


                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="text/javascript"><!--

        $('#tabs a:first').tab('show');
        $('.refund_button').on('click', function (e) {
            var order_total = $(this).data('total') - $(this).data('refunded');
            var total_refunded = prompt('Enter refund amount:', order_total);
            if (parseInt(total_refunded) > 0) {
                call_areto_action_refund(this, total_refunded);
            }
        });

        function call_areto_action_refund(el, total_refunded) {
            var order_id = $(el).data('order-id');
            var internal_order_id = $(el).data('internal-order-id');
            var current_label = $(el).val();
            $(el).attr('disabled', 'disabled');
            $(el).val('<?php echo $text_wait; ?>');

            $.ajax({
                url: '<?php echo html_entity_decode($action, ENT_QUOTES, 'UTF-8'); ?>',
                type: 'POST',
                cache: false,
                async: true,
                dataType: 'json',
                data: {
                    action: 'refund',
                    order_id: order_id,
                    internal_order_id: internal_order_id,
                    total_refunded: total_refunded
                },
                success: function (response) {
                    if (response.status !== 'ok') {
                        alert('Error: ' + response.message);
                        $(el).removeAttr('disabled');
                        $(el).val(current_label);
                        return false;
                    }
                    $(el).val(response.label);
                }
            });
        }

        //--></script>
<?php echo $footer ?>
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
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab-settings">
                            <table class="table table-bordered table-hover">
                                <tr>
                                    <td>
                                        <label for="aretoqp_api_id"><?php echo $text_api_id; ?></label>
                                    </td>
                                    <td>
                                        <input type="text" name="aretoqp_api_id" id="aretoqp_api_id"
                                               value="<?php echo $aretoqp_api_id; ?>" autocomplete="off">
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <label for="api_session"><?php echo $text_api_session; ?></label>
                                    </td>
                                    <td>
                                        <input type="password" name="aretoqp_api_session" id="aretoqp_api_session"
                                               value="<?php echo $aretoqp_api_session; ?>" autocomplete="off">
                                    </td>
                                </tr>
								<tr>
									<td>
										<label for="aretoqp_terms_url"><?php echo $text_terms_url; ?></label>
									</td>
									<td>
										<input type="text" name="aretoqp_terms_url" id="aretoqp_terms_url"
											   value="<?php echo $aretoqp_terms_url; ?>" autocomplete="off">
									</td>
								</tr>
								<tr>
									<td>
										<label for="aretoqp_descriptor"><?php echo $text_descriptor; ?></label>
									</td>
									<td>
										<input type="text" name="aretoqp_descriptor" id="aretoqp_descriptor"
											   value="<?php echo $aretoqp_descriptor; ?>" autocomplete="off">
									</td>
								</tr>
                                <tr>
                                    <td><?php echo $text_total; ?></td>
                                    <td><input type="text" name="aretoqp_total" value="<?php echo $aretoqp_total; ?>"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td><?php echo $text_complete_status; ?></td>
                                    <td><select name="aretoqp_completed_status_id">
                                            <?php foreach ($order_statuses as $order_status): ?>
                                                <?php if ($order_status['order_status_id'] == $aretoqp_completed_status_id): ?>
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
                                    <td><select name="aretoqp_pending_status_id">
                                            <?php foreach ($order_statuses as $order_status): ?>
                                                <?php if ($order_status['order_status_id'] == $aretoqp_pending_status_id): ?>
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
                                    <td><select name="aretoqp_canceled_status_id">
                                            <?php foreach ($order_statuses as $order_status): ?>
                                                <?php if ($order_status['order_status_id'] == $aretoqp_canceled_status_id): ?>
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
                                    <td><select name="aretoqp_failed_status_id">
                                            <?php foreach ($order_statuses as $order_status): ?>
                                                <?php if ($order_status['order_status_id'] == $aretoqp_failed_status_id): ?>
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
                                    <td><select name="aretoqp_refunded_status_id">
                                            <?php foreach ($order_statuses as $order_status): ?>
                                                <?php if ($order_status['order_status_id'] == $aretoqp_refunded_status_id): ?>
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
                                    <td><select name="aretoqp_geo_zone_id">
                                            <option value="0"><?php echo $text_all_zones; ?></option>
                                            <?php foreach ($geo_zones as $geo_zone): ?>
                                                <?php if ($geo_zone['geo_zone_id'] == $aretoqp_geo_zone_id): ?>
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
                                    <td><select name="aretoqp_status">
                                            <?php if ($aretoqp_status): ?>
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
                                    <td><input type="text" name="aretoqp_sort_order"
                                               value="<?php echo $aretoqp_sort_order; ?>"
                                               size="1"/></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php echo $footer ?>
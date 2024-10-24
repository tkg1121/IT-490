<?php if (isset($data)) : ?>
    <?php
    // Setup some variables for readability
    $_extra_classes = se($data, "extra_classes", "", false);
    $_title = se($data, "title", "", false);
    $_data = isset($data["data"]) ? $data["data"] : [];
    if (!$_data) {
        $_data = [];
    }
    $_view_url = se($data, "view_url", "", false);
    $_view_label = se($data, "view_label", "View", false);
    $_view_classes = se($data, "view_classes", "btn btn-primary", false);
    $_edit_url = se($data, "edit_url", "", false);
    $_edit_label = se($data, "edit_label", "Edit", false);
    $_edit_classes = se($data, "edit_classes", "btn btn-secondary", false);
    $_delete_url = se($data, "delete_url", "", false);
    $_delete_label = se($data, "delete_label", "Delete", false);
    $_delete_classes = se($data, "delete_classes", "btn btn-danger", false);
    $_primary_key_column = se($data, "primary_key", "id", false); // Used for the URL generation
    $_post_self_form = isset($data["post_self_form"]) ? $data["post_self_form"] : [];
    $_has_at_least_one_url = $_view_url || $_edit_url || $_delete_url || $_post_self_form;
    $_empty_message = se($data, "empty_message", "No records to show", false);
    $_header_override = isset($data["header_override"]) ? $data["header_override"] : []; // Note: this is as a CSV string or an array
    // Assumes CSV list; explodes to an array
    if (is_string($_header_override)) {
        $_header_override = explode(",", $_header_override);
    }
    $_ignored_columns = isset($data["ignored_columns"]) ? $data["ignored_columns"] : []; // Note: this is as a CSV string or an array
    // Assumes CSV list; explodes to an array
    if (is_string($_ignored_columns)) {
        $_ignored_columns = explode(",", $_ignored_columns);
    }
    // Attempt to get headers from $_data if no override
    if (!$_header_override && count($_data) > 0) {
        $_header_override = array_filter(array_keys($_data[0]), function ($v) use ($_ignored_columns) {
            return !in_array($v, $_ignored_columns);
        });
    }
    ?>

    <?php if ($_title) : ?>
        <h3><?php echo $_title; ?></h3>
    <?php endif; ?>
    <table class="table <?php echo $_extra_classes; ?>">
        <?php if ($_header_override) : ?>
            <thead>
                <?php foreach ($_header_override as $h) : ?>
                    <th><?php echo $h; ?></th>
                <?php endforeach; ?>
                <?php if ($_has_at_least_one_url) : ?>
                    <th>Actions</th>
                <?php endif; ?>
            </thead>
        <?php endif; ?>
        <tbody>
            <?php if (is_array($_data) && count($_data) > 0) : ?>
                <?php foreach ($_data as $row) : ?>
                    <tr>
                        <?php foreach (array_values($row) as $v) : ?>
                            <?php if (!in_array($v, $_ignored_columns)) : ?>
                                <td><?php echo $v; ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($_has_at_least_one_url) : ?>
                            <td>
                                <?php if ($_view_url) : ?>
                                    <a href="<?php echo $_view_url; ?>?<?php echo $_primary_key_column; ?>=<?php echo $row[$_primary_key_column]; ?>" class="<?php echo $_view_classes; ?>"><?php echo $_view_label; ?></a>
                                <?php endif; ?>
                                <?php if ($_edit_url) : ?>
                                    <a href="<?php echo $_edit_url; ?>?<?php echo $_primary_key_column; ?>=<?php echo $row[$_primary_key_column]; ?>" class="<?php echo $_edit_classes; ?>"><?php echo $_edit_label; ?></a>
                                <?php endif; ?>
                                <?php if ($_delete_url) : ?>
                                    <a href="<?php echo $_delete_url; ?>?<?php echo $_primary_key_column; ?>=<?php echo $row[$_primary_key_column]; ?>" class="<?php echo $_delete_classes; ?>"><?php echo $_delete_label; ?></a>
                                <?php endif; ?>
                                <?php if ($_post_self_form) : ?>
                                    <!-- TODO refactor -->
                                    <form method="POST">
                                        <input type="hidden" name="<?php echo se($_post_self_form, "name", $_primary_key_column); ?>" value="<?php echo $row[$_primary_key_column]; ?>" />
                                        <input type="submit" class="<?php echo se($_post_self_form, "classes"); ?>" value="<?php echo se($_post_self_form, "label", "Submit"); ?>" />
                                    </form>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="100%"><?php echo $_empty_message; ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>
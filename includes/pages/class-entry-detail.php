<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}
/**
 * Gravity Flow Entry Detail
 *
 *
 * @package     GravityFlow
 * @subpackage  Classes/Gravity_Flow
 * @copyright   Copyright (c) 2015-2016, Steven Henty
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0
 */
class Gravity_Flow_Entry_Detail {

	/**
	 * @param $form
	 * @param $entry
	 * @param null|Gravity_Flow_Step $current_step
	 * @param array $args
	 */
	public static function entry_detail( $form, $entry, $current_step = null, $args = array() ) {

		// In case fields need the GFEntryDetail class
		require_once( GFCommon::get_base_path() . '/entry_detail.php' );

		$form_id      = absint( $form['id'] );
		$ajax         = false;
		$field_values = null;
		$form         = apply_filters( 'gform_pre_render', $form, $ajax, $field_values );
		$form         = apply_filters( 'gform_pre_render_' . $form_id, $form, $ajax, $field_values );
		$args         = self::get_args( $args );

		$display_empty_fields         = (bool) $args['display_empty_fields'];
		$check_view_entry_permissions = (bool) $args['check_permissions'];
		$show_timeline                = (bool) $args['timeline'];
		$display_instructions         = (bool) $args['display_instructions'];
		$sidebar                      = (bool) $args['sidebar'];

		self::include_scripts();

		?>

		<div class="wrap gf_entry_wrap gravityflow_workflow_wrap gravityflow_workflow_detail">

			<?php
			self::maybe_show_header( $form, $args );

			if ( $check_view_entry_permissions ) {
				$permission_granted = self::is_permission_granted( $entry, $form, $current_step );

				if ( ! $permission_granted ) {
					$permission_denied_message = esc_attr__( "You don't have permission to view this entry.", 'gravityflow' );
					$permission_denied_message = apply_filters( 'gravityflow_permission_denied_message_entry_detail', $permission_denied_message, $current_step );
					echo $permission_denied_message;

					return;
				}
			}

			$url     = remove_query_arg( array( 'gworkflow_token', 'new_status' ) );
			$classes = self::get_classes( $args );

			?>
				<form id="gform_<?php echo $form_id; ?>" method="post" enctype='multipart/form-data' action="<?php echo esc_url( $url ); ?>">
					<?php wp_nonce_field( 'gforms_save_entry', 'gforms_save_entry' ) ?>
					<input type="hidden" name="step_id" value="<?php echo $current_step ? $current_step->get_id() : ''; ?>" />
					<div id="poststuff">
						<div id="post-body" class="metabox-holder <?php echo $classes; ?>">
							<div id="post-body-content">
								<?php

								do_action( 'gravityflow_entry_detail_content_before', $form, $entry );

								$editable_fields = array();

								if ( $current_step ) {
									$can_update      = self::can_update( $current_step );
									$editable_fields = $can_update ? $current_step->get_editable_fields() : array();

									self::maybe_show_instructions( $can_update, $display_instructions, $current_step, $form, $entry );
								}

								self::entry_detail_grid( $form, $entry, $display_empty_fields, $editable_fields, $current_step );

								do_action( 'gravityflow_entry_detail', $form, $entry, $current_step );

								if ( ! $sidebar ) {
									gravity_flow()->workflow_entry_detail_status_box( $form, $entry, $current_step, $args );
									self::print_button( $entry, $show_timeline, $check_view_entry_permissions );
								}
								?>

							</div>
							<div id="postbox-container-1" class="postbox-container">

							<?php
							if ( $sidebar ) {
								gravity_flow()->workflow_entry_detail_status_box( $form, $entry, $current_step, $args );
								self::print_button( $entry, $show_timeline, $check_view_entry_permissions );
							}

							?>
							</div>
							<?php
							self::maybe_show_timeline( $entry, $form, $show_timeline );
							?>
						</div>

					</div>

				</form>

		</div>
		<?php
	}

	/**
	 * Merges the specified arguments with the defaults.
	 *
	 * @param array $args The arguments specified when calling the detail page.
	 *
	 * @return array
	 */
	public static function get_args( $args ) {
		$defaults = array(
			'display_empty_fields' => true,
			'check_permissions'    => true,
			'show_header'          => true,
			'timeline'             => true,
			'display_instructions' => true,
			'sidebar'              => true,
			'step_status'          => true,
			'workflow_info'        => true,
		);

		$args = array_merge( $defaults, $args );
		gravity_flow()->log_debug( __METHOD__ . '() args: ' . print_r( $args, true ) );

		return $args;
	}

	/**
	 * Outputs the inline scripts.
	 */
	public static function include_scripts() {
		?>

		<script type="text/javascript">

			if (typeof ajaxurl == 'undefined') {
				ajaxurl = <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			}

			function DeleteFile(leadId, fieldId, deleteButton) {
				if (confirm(<?php echo json_encode( __( "Would you like to delete this file? 'Cancel' to stop. 'OK' to delete", 'gravityflow' ) ); ?>)) {
					var fileIndex = jQuery(deleteButton).parent().index();
					var mysack = new sack("<?php echo admin_url( 'admin-ajax.php' )?>");
					mysack.execute = 1;
					mysack.method = 'POST';
					mysack.setVar("action", "rg_delete_file");
					mysack.setVar("rg_delete_file", "<?php echo wp_create_nonce( 'rg_delete_file' ) ?>");
					mysack.setVar("lead_id", leadId);
					mysack.setVar("field_id", fieldId);
					mysack.setVar("file_index", fileIndex);
					mysack.onError = function () {
						alert(<?php echo json_encode( __( 'Ajax error while deleting file.', 'gravityflow' ) ) ?>)
					};
					mysack.runAJAX();

					return true;
				}
			}

			function EndDeleteFile(fieldId, fileIndex) {
				var previewFileSelector = "#preview_existing_files_" + fieldId + " .ginput_preview";
				var $previewFiles = jQuery(previewFileSelector);
				var rr = $previewFiles.eq(fileIndex);
				$previewFiles.eq(fileIndex).remove();
				var $visiblePreviewFields = jQuery(previewFileSelector);
				if ($visiblePreviewFields.length == 0) {
					jQuery('#preview_' + fieldId).hide();
					jQuery('#upload_' + fieldId).show('slow');
				}
			}

			function ToggleShowEmptyFields() {
				if (jQuery("#gentry_display_empty_fields").is(":checked")) {
					createCookie("gf_display_empty_fields", true, 10000);
					document.location = document.location.href;
				}
				else {
					eraseCookie("gf_display_empty_fields");
					document.location = document.location.href;
				}
			}

			function createCookie(name, value, days) {
				if (days) {
					var date = new Date();
					date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
					var expires = "; expires=" + date.toGMTString();
				}
				else var expires = "";
				document.cookie = name + "=" + value + expires + "; path=/";
			}

			function eraseCookie(name) {
				createCookie(name, "", -1);
			}

		</script>
		<?php
	}

	/**
	 * Output the header, if enabled.
	 *
	 * @param array $form The current form.
	 * @param array $args The arguments to be used when rendering the page.
	 */
	public static function maybe_show_header( $form, $args ) {
		$show_header = (bool) $args['show_header'];
		if ( ! $show_header ) {
			return;
		}

		?>
		<h2 class="gf_admin_page_title">
			<img width="45" height="22"
			     src="<?php echo gravity_flow()->get_base_url(); ?>/images/gravityflow-icon-blue-grad.svg"
			     style="margin-right:5px;"/>
			<?php esc_html_e( $form['title'] ); ?><span
				class="gf_admin_page_formid">ID: <?php echo absint( $form['id'] ); ?></span>
		</h2>

		<div id="gf_form_toolbar">
			<ul id="gf_form_toolbar_links">

				<?php

				$menu_items = gravity_flow()->get_toolbar_menu_items();

				echo GFForms::format_toolbar_menu_items( $menu_items );

				?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Checks if the current user has permission to view the entry details.
	 *
	 * @param array $entry The current entry.
	 * @param array $form The current form.
	 * @param Gravity_Flow_Step|null $current_step The step currently being displayed.
	 *
	 * @return bool
	 */
	public static function is_permission_granted( $entry, $form, $current_step ) {
		global $current_user;

		$permission_granted = false;
		$assignee_key       = '';
		$user_id            = $current_user->ID;

		if ( empty( $user_id ) ) {
			if ( $token = gravity_flow()->decode_access_token() ) {
				$assignee_key = sanitize_text_field( $token['sub'] );
				list( $type, $user_id ) = rgexplode( '|', $assignee_key, 2 );
			}
		} else {
			$assignee_key = 'user_id|' . $user_id;
		}

		gravity_flow()->log_debug( __METHOD__ . '() checking permissions.  $current_user->ID: ' . $current_user->ID . ' created_by: ' . $entry['created_by'] . ' assignee key: ' . $assignee_key );

		if ( ! empty( $user_id ) && $entry['created_by'] == $user_id ) {
			$permission_granted = true;
		} else {

			$is_assignee = $current_step ? $current_step->is_assignee( $assignee_key ) : false;

			$full_access = GFAPI::current_user_can_any( array(
				'gform_full_access',
				'gravityflow_status_view_all',
			) );

			gravity_flow()->log_debug( __METHOD__ . '() $full_access: ' . ( $full_access ? 'yes' : 'no' ) );

			if ( $is_assignee || $full_access ) {
				$permission_granted = true;
			}
		}

		/**
		 * Allows the the permission check to be overridden for the workflow entry detail page.
		 *
		 * @param bool $permission_granted Whether permission is granted to open the entry.
		 * @param array $entry
		 * @param array $form
		 * @param Gravity_Flow_Step $current_step
		 */
		$permission_granted = apply_filters( 'gravityflow_permission_granted_entry_detail', $permission_granted, $entry, $form, $current_step );

		return $permission_granted;
	}

	/**
	 * Determines if the role or status permits the user to update field values on this step.
	 *
	 * @param Gravity_Flow_Step $current_step The step this entry is currently on.
	 *
	 * @return bool
	 */
	public static function can_update( $current_step ) {
		$current_user_status = $current_step->get_user_status();
		$current_role_status = false;
		if ( $current_step ) {
			foreach ( gravity_flow()->get_user_roles() as $role ) {
				$current_role_status = $current_step->get_role_status( $role );
				if ( $current_role_status == 'pending' ) {
					break;
				}
			}
		}

		return $current_user_status == 'pending' || $current_role_status == 'pending';
	}

	/**
	 * Displays the step instructions, if appropriate.
	 *
	 * @param bool $can_update Indicates if the user can edit field values on this step.
	 * @param bool $display_instructions Indicates if the step instructions should be displayed
	 * @param Gravity_Flow_Step $current_step The step this entry is currently on.
	 * @param array $form The current form.
	 * @param array $entry The current entry.
	 */
	public static function maybe_show_instructions( $can_update, $display_instructions, $current_step, $form, $entry ) {
		if ( $can_update && $display_instructions && $current_step->instructionsEnable ) {
			$nl2br = apply_filters( 'gravityflow_auto_format_instructions', true );
			$nl2br = apply_filters( 'gravityflow_auto_format_instructions_' . $form['id'], $nl2br );

			$instructions = $current_step->instructionsValue;
			$instructions = GFCommon::replace_variables( $instructions, $form, $entry, false, true, $nl2br );
			$instructions = $current_step->replace_variables( $instructions, null );
			$instructions = wp_kses_post( $instructions );

			?>
			<div class="postbox gravityflow-instructions">
				<div class="inside">
					<?php echo $instructions; ?>
				</div>
			</div>

			<?php
		}
	}

	/**
	 * Retrieve the css classes to be added to the div#post-body.
	 *
	 * @param array $args The arguments to be used when rendering the page.
	 *
	 * @return string
	 */
	public static function get_classes( $args ) {
		$sidebar               = (bool) $args['sidebar'];
		$display_workflow_info = (bool) $args['workflow_info'];
		$display_step_info     = (bool) $args['step_status'];

		$classes = $sidebar ? 'columns-2' : 'columns-1';
		if ( $sidebar ) {
			$classes .= ' gravityflow-has-sidebar';
		} else {
			$classes .= ' gravityflow-no-sidebar';
		}

		if ( $display_workflow_info ) {
			$classes .= ' gravityflow-has-workflow-info';
		} else {
			$classes .= ' gravityflow-no-workflow-info';
		}

		if ( $display_step_info ) {
			$classes .= ' gravityflow-has-step-info';
		} else {
			$classes .= ' gravityflow-no-step-info';
		}

		return $classes;
	}

	public static function print_button( $entry, $show_timeline, $check_view_entry_permissions ) {

		if ( is_user_logged_in() || $check_view_entry_permissions ) :
			?>

			<!-- begin print button -->
			<div class="detail-view-print">
				<a href="javascript:;"
				   onclick="var notes_qs = jQuery('#gform_print_notes').is(':checked') ? '&timelines=1' : ''; var url='<?php echo admin_url( 'admin-ajax.php' ) ?>?action=gravityflow_print_entries&lid=<?php echo absint( $entry['id'] ); ?>' + notes_qs; printPage(url);"
				   class="button"><?php esc_html_e( 'Print', 'gravityflow' ) ?></a>

				<?php if ( $show_timeline ) { ?>

					<input type="checkbox" name="print_notes" value="print_notes" checked="checked"
					       id="gform_print_notes"/>
					<label for="print_notes"><?php esc_html_e( 'include timeline', 'gravityflow' ) ?></label>
				<?php } ?>

			</div>
			<!-- end print button -->

		<?php endif;
	}

	/**
	 * Displays the timeline notes, if enabled.
	 *
	 * @param array $entry The current entry.
	 * @param array $form The current form.
	 * @param bool $show_timeline Indicates if the timeline should be displayed.
	 */
	public static function maybe_show_timeline( $entry, $form, $show_timeline ) {
		if ( ! $show_timeline ) {
			return;
		}

		?>
		<div id="postbox-container-2" class="postbox-container">
			<div class="postbox gravityflow-timeline">
				<h3>
					<label for="name"><?php esc_html_e( 'Timeline', 'gravityflow' ); ?></label>
				</h3>

				<div class="inside">
					<?php self::timeline( $entry, $form ); ?>
				</div>

			</div>
		</div>
		<?php
	}

	public static function timeline( $entry, $form ) {
		$notes = self::get_timeline_notes( $entry );

		//getting email values
		$email_fields = GFCommon::get_email_fields( $form );
		$emails       = array();

		foreach ( $email_fields as $email_field ) {
			if ( ! empty( $entry[ $email_field->id ] ) ) {
				$emails[] = $entry[ $email_field->id ];
			}
		}
		//displaying notes grid
		$subject = '';
		self::notes_grid( $notes, true, $emails, $subject );
	}

	public static function get_timeline_notes( $entry ) {
		$notes = RGFormsModel::get_lead_notes( $entry['id'] );

		foreach ( $notes as $key => $note ) {
			if ( $note->note_type !== 'gravityflow' ) {
				unset( $notes[ $key ] );
			}
		}

		reset( $notes );

		$initial_note               = new stdClass();
		$initial_note->id           = 0;
		$initial_note->date_created = $entry['date_created'];
		$initial_note->value        = esc_html__( 'Workflow Submitted', 'gravityflow' );
		$initial_note->user_id      = $entry['created_by'];
		$user                       = get_user_by( 'id', $entry['created_by'] );
		$initial_note->user_name    = $user ? $user->display_name : $entry['ip'];

		array_unshift( $notes, $initial_note );

		$notes = array_reverse( $notes );

		return $notes;
	}


	/**
	 * @param $form
	 * @param $entry
	 * @param bool|false $allow_display_empty_fields
	 * @param array $editable_fields
	 * @param Gravity_Flow_Step|null $current_step
	 */
	public static function entry_detail_grid( $form, $entry, $allow_display_empty_fields = false, $editable_fields = array(), $current_step = null ) {
		$form_id = absint( $form['id'] );

		$display_empty_fields = false;
		if ( $allow_display_empty_fields ) {
			$display_empty_fields = rgget( 'gf_display_empty_fields', $_COOKIE );
		}

		$display_empty_fields = (bool) apply_filters( 'gravityflow_entry_detail_grid_display_empty_fields', $display_empty_fields, $form, $entry );

		?>

		<input type="hidden" name="action" id="action" value="" />
		<input type="hidden" name="save" id="action" value="Update" />
		<input type="hidden" name="screen_mode" id="screen_mode" value="<?php echo esc_attr( rgpost( 'screen_mode' ) ) ?>" />

		<table cellspacing="0" class="widefat fixed entry-detail-view">
			<thead>
			<tr>
				<th id="details">
					<?php
					$title = sprintf( '%s : %s %s', esc_html( $form['title'] ), __( 'Entry # ', 'gravityflow' ), absint( $entry['id'] ) );
					echo apply_filters( 'gravityflow_title_entry_detail', $title, $form, $entry );
					?>
				</th>
				<th style="width:140px; font-size:10px; text-align: right;">
					<?php
					if ( $allow_display_empty_fields ) {
						?>
						<input type="checkbox" id="gentry_display_empty_fields" <?php echo $display_empty_fields ? "checked='checked'" : '' ?> onclick="ToggleShowEmptyFields();" />&nbsp;&nbsp;
						<label for="gentry_display_empty_fields"><?php _e( 'show empty fields', 'gravityflow' ) ?></label>
					<?php
					}
					?>
				</th>
			</tr>
			</thead>

			<?php
			if ( empty( $editable_fields ) ) {
				?>
				<tbody>
				<?php
				self::fields( $form, $entry, $display_empty_fields, $current_step, 'table' );
				?>
				</tbody>
				<?php
			} else {
				self::entry_editor( $form, $entry, $current_step, $display_empty_fields );
			}

			?>
			</table>

	<?php
	}

	/**
	 * Handles displaying the relevant non-editable and editable fields for the current step.
	 *
	 * @param array $form The current form.
	 * @param array $entry The current entry.
	 * @param Gravity_Flow_Step $current_step The step this entry is currently on.
	 * @param bool $display_empty_fields Indicates if fields without a value should be displayed.
	 */
	public static function entry_editor( $form, $entry, $current_step, $display_empty_fields ) {
		?>
		<tbody>
			<tr>
				<td colspan="2">
					<?php
					require_once( 'class-entry-editor.php' );
					$entry_editor = new Gravity_Flow_Entry_Editor( $form, $entry, $current_step, $display_empty_fields );
					$entry_editor->render_edit_form();
					?>
				</td>
			</tr>
			<?php
			if ( $entry_editor->has_product_fields ) {
				self::maybe_show_products_summary( $form, $entry, $current_step );
			}
			?>
		</tbody>
		<?php
	}

	/**
	 * Displays the products summary table if enabled for the current step.
	 *
	 * @param array $form The current form.
	 * @param array $entry The current entry.
	 * @param Gravity_Flow_Step $current_step The step this entry is currently on.
	 */
	public function maybe_show_products_summary( $form, $entry, $current_step ) {
		$summary_enabled = true;
		if ( $current_step ) {
			$meta = $current_step->get_feed_meta();
			if ( isset( $meta['display_order_summary'] ) && ! $current_step->display_order_summary ) {
				$summary_enabled = false;
			}
		}

		if ( $summary_enabled ) {
			$products = GFCommon::get_product_fields( $form, $entry );

			if ( ! empty( $products['products'] ) ) {
				$form_id               = $form['id'];
				$product_summary_label = apply_filters( 'gform_order_label', __( 'Order', 'gravityflow' ), $form_id );
				$product_summary_label = apply_filters( "gform_order_label_{$form_id}", $product_summary_label, $form_id );
				?>
				<tr>
					<td colspan="2" class="gravityflow-order-summary"><?php echo $product_summary_label; ?></td>
				</tr>
				<tr>
					<td colspan="2" class="entry-view-field-value lastrow">
						<?php self::products_summary( $form, $entry, $products ) ?>
					</td>
				</tr>

				<?php
			}
		}
	}

	/**
	 * @param array $form
	 * @param array $entry
	 * @param bool $display_empty_fields
	 * @param Gravity_Flow_Step|null $current_step
	 * @param $format
	 */
	public static function fields( $form, $entry, $display_empty_fields, $current_step, $format ) {
		$form_id                 = absint( $form['id'] );
		$count                   = 0;
		$field_count             = sizeof( $form['fields'] );
		$has_product_fields      = false;
		$display_fields_mode     = $current_step ? $current_step->display_fields_mode : 'all_fields';
		$display_fields_selected = $current_step && is_array( $current_step->display_fields_selected ) ? $current_step->display_fields_selected : array();

		foreach ( $form['fields'] as &$field ) {
			/* @var GF_Field $field */

			// Not needed as we're always adminOnly
			$field->adminOnly = false;

			$is_product_field = GFCommon::is_product_field( $field->type );

			$display_field = true;

			if ( $display_fields_mode == 'selected_fields' ) {
				if ( ! in_array( $field->id, $display_fields_selected ) ) {
					$display_field = false;
				}
			} else {
				if ( GFFormsModel::is_field_hidden( $form, $field, array(), $entry ) || $is_product_field ) {
					$display_field = false;
				}
			}

			$display_field = (bool) apply_filters( 'gravityflow_workflow_detail_display_field', $display_field, $field, $form, $entry, $current_step );

			switch ( RGFormsModel::get_input_type( $field ) ) {
				case 'section' :

					if ( ! GFCommon::is_section_empty( $field, $form, $entry ) || $display_empty_fields ) {
						$count ++;
						$is_last = $count >= $field_count ? true : false;
						?>
						<tr>
							<td colspan="2"
							    class="entry-view-section-break<?php echo $is_last ? ' lastrow' : '' ?>"><?php echo esc_html( rgar( $field, 'label' ) ) ?></td>
						</tr>
						<?php
					}

					break;

				case 'captcha':
				case 'password':
				case 'page':
					//ignore captcha, password, page field
					break;

				case 'html':
					if ( $display_field ) {
						$content = GFCommon::replace_variables( $field->content, $form, $entry, false, true, false, 'html' );
						$content = do_shortcode( $content );
						?>
						<tr>
							<td colspan="2" class="entry-view-field-value"><?php echo $content ?></td>
						</tr>
						<?php
					}

					break;
				default :

					if ( $is_product_field ) {
						$has_product_fields = true;
					}

					if ( ! $display_field ) {
						continue;
					}

					$value = RGFormsModel::get_lead_field_value( $entry, $field );

					if ( $field->type == 'product' ) {
						if ( $field->has_calculation() ) {
							$product_name = trim( $value[ $field->id . '.1' ] );
							$price        = trim( $value[ $field->id . '.2' ] );
							$quantity     = trim( $value[ $field->id . '.3' ] );

							if ( empty( $product_name ) ) {
								$value[ $field->id . '.1' ] = $field->get_field_label( false, $value );
							}

							if ( empty( $price ) ) {
								$value[ $field->id . '.2' ] = '0';
							}

							if ( empty( $quantity ) ) {
								$value[ $field->id . '.3' ] = '0';
							}
						}
					}

					$input_type = $field->get_input_type();
					if ( $input_type == 'hiddenproduct' ) {
						$display_value = $value[ $field->id . '.2' ];
					} else {
						$display_value = GFCommon::get_lead_field_display( $field, $value, $entry['currency'] );
					}

					$display_value = apply_filters( 'gform_entry_field_value', $display_value, $field, $entry, $form );

					if ( $display_empty_fields || ! empty( $display_value ) || $display_value === '0' ) {
						$count ++;
						$is_last  = $count >= $field_count && ! $has_product_fields ? true : false;
						$last_row = $is_last ? ' lastrow' : '';

						$display_value = empty( $display_value ) && $display_value !== '0' ? '&nbsp;' : $display_value;

						$content = '
                                <tr>
                                    <td colspan="2" class="entry-view-field-name">' . esc_html( GFCommon::get_label( $field, 0, false, false ) ) . '</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="entry-view-field-value' . $last_row . '">' . $display_value . '</td>
                                </tr>';

						$content = apply_filters( 'gform_field_content', $content, $field, $value, $entry['id'], $form['id'] );
						echo $content;
					}

					break;
			}
		}

		$summary_enabled = true;
		if ( $current_step ) {
			$meta = $current_step->get_feed_meta();
			if ( isset( $meta['display_order_summary'] ) && ! $current_step->display_order_summary ) {
				$summary_enabled = false;
			}
		}

		if ( $has_product_fields && $summary_enabled ) {

			$products = GFCommon::get_product_fields( $form, $entry );

			if ( ! empty( $products['products'] ) ) {
				$product_summary_label = apply_filters( "gform_order_label_{$form_id}", apply_filters( 'gform_order_label', __( 'Order', 'gravityflow' ), $form_id ), $form_id );
				if ( $format == 'table' ) {
					?>
					<tr>
						<td colspan="2" class="entry-view-field-name"><?php echo $product_summary_label; ?></td>
					</tr>
					<tr>
						<td colspan="2" class="entry-view-field-value lastrow">
							<?php self::products_summary( $form, $entry, $products ) ?>
						</td>
					</tr>

					<?php
				}
			}
		}

	}

	public static function products_summary( $form, $entry, $products ) {
		$form_id = absint( $form['id'] );
		?>
		<table class="entry-products" cellspacing="0" width="97%">
			<colgroup>
				<col class="entry-products-col1" />
				<col class="entry-products-col2" />
				<col class="entry-products-col3" />
				<col class="entry-products-col4" />
			</colgroup>
			<thead>
			<th scope="col"><?php echo apply_filters( "gform_product_{$form_id}", apply_filters( 'gform_product', __( 'Product', 'gravityflow' ), $form_id ), $form_id ); ?></th>
			<th scope="col" class="textcenter"><?php echo esc_html( apply_filters( "gform_product_qty_{$form_id}", apply_filters( 'gform_product_qty', __( 'Qty', 'gravityflow' ), $form_id ), $form_id ) ); ?></th>
			<th scope="col"><?php echo esc_html( apply_filters( "gform_product_unitprice_{$form_id}", apply_filters( 'gform_product_unitprice', __( 'Unit Price', 'gravityflow' ), $form_id ), $form_id ) ); ?></th>
			<th scope="col"><?php echo esc_html( apply_filters( "gform_product_price_{$form_id}", apply_filters( 'gform_product_price', __( 'Price', 'gravityflow' ), $form_id ), $form_id ) ); ?></th>
			</thead>
			<tbody>
			<?php

			$total = 0;
			foreach ( $products['products'] as $product ) {
				if ( empty( $product['name'] ) ) {
					continue;
				}
				?>
				<tr>
					<td>
						<div class="product_name"><?php echo esc_html( $product['name'] ); ?></div>
						<ul class="product_options">
							<?php
							$price = GFCommon::to_number( $product['price'] );
							if ( is_array( rgar( $product, 'options' ) ) ) {
								$count = sizeof( $product['options'] );
								$index = 1;
								foreach ( $product['options'] as $option ) {
									$price += GFCommon::to_number( $option['price'] );
									$class = $index == $count ? " class='lastitem'" : '';
									$index ++;
									?>
									<li<?php echo $class ?>><?php echo $option['option_label'] ?></li>
									<?php
								}
							}
							$subtotal = floatval( $product['quantity'] ) * $price;
							$total += $subtotal;
							?>
						</ul>
					</td>
					<td class="textcenter"><?php echo esc_html( $product['quantity'] ); ?></td>
					<td><?php echo GFCommon::to_money( $price, $entry['currency'] ) ?></td>
					<td><?php echo GFCommon::to_money( $subtotal, $entry['currency'] ) ?></td>
				</tr>
				<?php
			}
			$total += floatval( $products['shipping']['price'] );
			?>
			</tbody>
			<tfoot>
			<?php
			if ( ! empty( $products['shipping']['name'] ) ) {
				?>
				<tr>
					<td colspan="2" rowspan="2" class="emptycell">&nbsp;</td>
					<td class="textright shipping"><?php echo esc_html( $products['shipping']['name'] ); ?></td>
					<td class="shipping_amount"><?php echo GFCommon::to_money( $products['shipping']['price'], $entry['currency'] ) ?>&nbsp;</td>
				</tr>
				<?php
			}
			?>
			<tr>
				<?php
				if ( empty( $products['shipping']['name'] ) ) {
					?>
					<td colspan="2" class="emptycell">&nbsp;</td>
					<?php
				}
				?>
				<td class="textright grandtotal"><?php _e( 'Total', 'gravityflow' ) ?></td>
				<td class="grandtotal_amount"><?php echo GFCommon::to_money( $total, $entry['currency'] ) ?></td>
			</tr>
			</tfoot>
		</table>
		<?php
	}

	public static function notes_grid( $notes, $is_editable, $emails = null, $subject = '' ) {

		if ( empty( $notes ) ) {
			return;
		}

		foreach ( $notes as $note ) {

			?>

			<div id="gravityflow-note-<?php echo $note->id; ?>" class="gravityflow-note gravityflow-note-<?php echo $note->user_name; ?>">
				<div class="gravityflow-note-avatar">
					<div>
						<?php

						if ( empty( $note->user_id ) ) {

							$img_url = '';

							if ( $note->user_name !== 'gravityflow' ) {
								$step = Gravity_Flow_Steps::get( $note->user_name );
								if ( $step ) {
									$img_url = $step->get_icon_url();
								}
							}

							if ( empty( $img_url ) ) {
								$img_url = gravity_flow()->get_base_url() . '/images/gravityflow-icon-blue.svg';
							}

							if ( strpos( $img_url, 'http' ) !== false ) {
								printf( '<img class="avatar avatar-65 photo" src="%s" style="width:65px;height:65px;" />', $img_url );
							} else {
								printf( '<span class="avatar avatar-65 photo">%s</span>', $img_url );
							}
						} else {
							echo get_avatar( $note->user_id, 65 );
						}

						?>
					</div>
					<div></div>
				</div>

				<div class="gravityflow-note-body-wrap">
					<div class="gravityflow-note-body">
						<div class="gravityflow-note-header">

							<div class="gravityflow-note-title">
								<?php

								if ( empty( $note->user_id ) ) {
									if ( $note->user_name == 'gravityflow' ) {
										echo esc_html( gravity_flow()->translate_navigation_label( 'Workflow' ) );
									} else {
										$step = Gravity_Flow_Steps::get( $note->user_name );
										if ( $step ) {
											echo $step->get_label();
										} else {
											echo esc_html( $note->user_name );
										}
									}
								} else {
									echo esc_html( $note->user_name );
								}

								?>
							</div>
							<div class="gravityflow-note-meta">
								<?php echo esc_html( GFCommon::format_date( $note->date_created, false, 'd M Y g:i a', false ) ) ?>
							</div>
						</div>

						<div class="gravityflow-note-body">
							<?php echo nl2br( esc_html( $note->value ) ) ?>
						</div>

					</div>
				</div>

			</div>
		<?php
		}
	}
}

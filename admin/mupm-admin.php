<?php
defined( 'MU_PLUGIN_MANAGER_PLUGIN_DIR' ) || exit;

require_once MU_PLUGIN_MANAGER_PLUGIN_DIR.'/admin/mupm-helper.php';

add_action( 'admin_init',function(){
  if( isset( $_GET['mupm-action'] ) && isset( $_GET['mu-plugin'] ) && isset( $_GET['_wpnonce'] ) ){
    $nonce = sanitize_text_field( $_GET['_wpnonce'] );
    $action = sanitize_text_field( $_GET['mupm-action'] );
    $mu_plugin = sanitize_text_field( $_GET['mu-plugin'] );
    switch( $action ){
      case 'eos-mu-deactivate':
        if( wp_verify_nonce( $nonce,'eos-deactivate-mu-plugin_'.$mu_plugin ) ){
          eos_mupm_deactivate_mu_plugin( $mu_plugin );
        }
        break;
      case 'eos-mu-activate':
        if( wp_verify_nonce( $nonce,'eos-activate-mu-plugin_'.$mu_plugin ) ){
          eos_mupm_activate_mu_plugin( $mu_plugin );
        }
        break;
      case 'eos-mu-delete':
        if( wp_verify_nonce( $nonce,'eos-delete-mu-plugin_'.$mu_plugin ) ){
          eos_mupm_delete_mu_plugin( $mu_plugin );
        }
        break;
    }
  }
} );

add_filter( 'plugin_action_links',function( $actions, $plugin_file, $plugin_data, $context ){
  if ( current_user_can( 'deactivate_plugin' ) && '.php' === substr( $plugin_file, -4 ) ) {
    $actions['deactivate'] = sprintf(
      '<a href="%s" id="deactivate-%s" aria-label="%s">%s</a>',
      wp_nonce_url( 'plugins.php?plugin_status=mustuse&amp;mupm-action=eos-mu-deactivate&amp;mu-plugin=' . urlencode( $plugin_file ), 'eos-deactivate-mu-plugin_'.$plugin_file ),
      esc_attr( $plugin_id_attr ),
      esc_attr( sprintf( _x( 'Deactivate %s', 'plugin' ), $plugin_data['Name'] ) ),
      __( 'Deactivate' )
    );
  }
  if ( current_user_can( 'activate_plugins' ) && '.php' !== substr( $plugin_file, -4 ) ) {
    $actions['activate'] = sprintf(
      '<a href="%s" id="activate-%s" class="edit" aria-label="%s">%s</a>',
      wp_nonce_url( 'plugins.php?plugin_status=mustuse&amp;mupm-action=eos-mu-activate&amp;mu-plugin=' . urlencode( $plugin_file ), 'eos-activate-mu-plugin_'.$plugin_file ),
      esc_attr( $plugin_id_attr ),
      esc_attr( sprintf( _x( 'Activate %s', 'plugin' ), $plugin_data['Name'] ) ),
      __( 'Activate' )
    );
  }
  if ( ! is_multisite() && current_user_can( 'delete_plugins' ) && '.php' !== substr( $plugin_file, -4 ) ) {
    $actions['delete'] = sprintf(
      '<a href="%s" id="delete-%s" class="delete" aria-label="%s">%s</a>',
      wp_nonce_url( 'plugins.php?plugin_status=mustuse&amp;mupm-action=eos-mu-delete&amp;mu-plugin=' . urlencode( $plugin_file ), 'eos-delete-mu-plugin_'.$plugin_file ),
      esc_attr( $plugin_id_attr ),
      esc_attr( sprintf( _x( 'Delete %s', 'plugin' ), $plugin_data['Name'] ) ),
      __( 'Delete' )
    );
  }
  return $actions;
},20,4 );

add_action( 'after_plugin_row',function(){
  //Add disabled mu-plugins after the active mu-plugins
  if( isset( $_GET['plugin_status'] ) && 'mustuse' === sanitize_text_field( $_GET['plugin_status'] ) ){
    static $mun = 0;
    ++$mun;
    $mu_plugins = wp_get_mu_plugins();
    if( $mu_plugins && is_array( $mu_plugins ) && $mun === count( array_keys( $mu_plugins ) ) ){
      $disabled_mus = eos_mupm_get_disabled_mu_plugins();
      if( $disabled_mus && is_array( $disabled_mus ) && !empty( $disabled_mus ) ){
        $mu_data = eos_mupm_get_disabled_mu_plugins_data();
        foreach( $disabled_mus as $disabled_mu ){
          $mu_info = $mu_data[sanitize_text_field( $disabled_mu )];
          $actions = apply_filters( 'plugin_action_links',array(), $disabled_mu,$mu_info,'mu-plugin' );
          $plugin_meta = array();
          $kses_args = array( 'a' => array( 'href' => array(),'class' => array(),'id' => array(),'aria-label' => array() ),'span' => array( 'class' => array() ) );
					if ( ! empty( $mu_info['Version'] ) ) {
						/* translators: %s: Plugin version number. */
						$plugin_meta[] = sprintf( __( 'Version %s' ), esc_attr( $mu_info['Version'] ) );
					}
					if ( ! empty( $mu_info['Author'] ) ) {
						$author = $mu_info['Author'];
						if ( ! empty( $mu_info['AuthorURI'] ) ) {
							$author = '<a href="' . esc_url( $mu_info['AuthorURI'] ) . '">' .esc_html(  $mu_info['Author'] ) . '</a>';
						}
						$plugin_meta[] = sprintf( __( 'By %s' ), $author );
					}
					if ( isset( $mu_info['slug'] ) && current_user_can( 'install_plugins' ) ) {
						$plugin_meta[] = sprintf(
							'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
							esc_url(
								network_admin_url(
									'plugin-install.php?tab=plugin-information&mu-plugin=' .esc_attr( $mu_info['slug'] ).
									'&TB_iframe=true&width=600&height=550'
								)
							),
							/* translators: %s: Plugin name. */
							esc_attr( sprintf( __( 'More information about %s' ), $plugin_name ) ),
							esc_attr( $plugin_name ),
							__( 'View details' )
						);
					} elseif ( ! empty( $mu_info['PluginURI'] ) ) {
						$aria_label = sprintf( __( 'Visit plugin site for %s' ),esc_attr( $plugin_name ) );

						$plugin_meta[] = sprintf(
							'<a href="%s" aria-label="%s">%s</a>',
							esc_url( $mu_info['PluginURI'] ),
							esc_attr( $aria_label ),
							__( 'Visit plugin site' )
						);
					}
        ?>
        <tr scope="row" class="inactive is-uninstallable">
          <th class="check-column"></th>
          <td class="plugin-title column-primary">
            <strong><?php echo isset( $mu_info['Title'] ) && '' !== sanitize_text_field( $mu_info['Title'] ) ? esc_html( $mu_info['Title'] ) : esc_html( str_replace( '.disabled','.php',$disabled_mu ) ); ?></strong>
            <div class="row-actions visible">
              <?php echo wp_kses( implode( ' | ',$actions ),$kses_args ); ?>
            </div>
          </td>
          <td class="column-description desc">
            <div class="plugin-description"><?php echo isset( $mu_info['Description'] ) ? esc_html( $mu_info['Description'] ) : ''; ?></div>
            <div><?php echo wp_kses( implode( ' | ', $plugin_meta ),$kses_args ); ?></div>
          </td>
        </tr>
        <?php
        }
      }
    }
  }
} );

add_action( 'in_admin_header',function(){
  ?>
  <script id="mu-plugin-manager">
  function eos_mupm_init(){
    var url='<?php echo esc_js( admin_url( 'plugins.php?plugin_status=mustuse' ) ); ?>';
    history.pushState({id: 'MU Plugin Manager',source:'mu-plugin-manager'},document.title,url);
  }
  eos_mupm_init();
  </script>
  <?php
} );

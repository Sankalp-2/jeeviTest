<?php
if ( !class_exists( 'Better_Messages_Rest_Api_DB_Migrate' ) ):

    class Better_Messages_Rest_Api_DB_Migrate
    {

        private $db_version = 0.1;

        public static function instance()
        {

            static $instance = null;

            if (null === $instance) {
                $instance = new Better_Messages_Rest_Api_DB_Migrate();
            }

            return $instance;
        }

        public function __construct(){
            add_action( 'rest_api_init',  array( $this, 'rest_api_init' ) );

            add_action( 'wp_ajax_bp_messages_admin_import_options', array( $this, 'import_admin_options' ) );
            add_action( 'wp_ajax_bp_messages_admin_export_options', array( $this, 'export_admin_options' ) );
        }

        public function export_admin_options(){

            $nonce    = $_POST['nonce'];
            if ( ! wp_verify_nonce($nonce, 'bpbm-import-options') ){
                exit;
            }

            if( ! current_user_can('manage_options') ){
                exit;
            }

            $options = get_option( 'bp-better-chat-settings', array() );
            wp_send_json(base64_encode(json_encode($options)));
        }

        public function import_admin_options(){

            $nonce    = $_POST['nonce'];
            if ( ! wp_verify_nonce($nonce, 'bpbm-import-options') ){
                exit;
            }

            if( ! current_user_can('manage_options') ){
                exit;
            }

            $settings = sanitize_text_field($_POST['settings']);

            $options  = base64_decode( $settings );
            $options  = json_decode( $options, true );

            if( is_null( $options ) ){
                wp_send_json_error('Error to decode data');
            } else {
                update_option( 'bp-better-chat-settings', $options );
                wp_send_json_success('Succesfully imported');
            }
        }


        public function rest_api_init(){
            /* register_rest_route( 'better-messages/v1', '/db/check', array(
                'methods' => 'GET',
                'callback' => array( $this, 'check_db' ),
                'permission_callback' => array( $this, 'has_access' )
            ) ); */
        }

        public function check_db(){
            $db_1_version = get_option('better_messages_db_version', false);
            $db_migrated  = get_option('better_messages_db_migrated', false);

            if( $db_1_version && ! $db_migrated ){
                return [
                    'result' => 'upgrade_required',
                    'from'   => (float) $db_1_version,
                    'to'     => $this->db_version
                ];
            }

            return [
                'result' => 'upgrade_not_required',
            ];
        }

        public function has_access(){
            return current_user_can( 'manage_options' );
        }

        public function install_tables(){
            global $wpdb;
            $sql             = array();
            $db_2_version = get_option( 'better_messages_2_db_version', 0 );

            $sqls = [
                '0.1' => [
                    "CREATE TABLE IF NOT EXISTS `" . bm_get_table('mentions') ."` (
                       `id` bigint(20) NOT NULL AUTO_INCREMENT,
                       `thread_id` bigint(20) NOT NULL,
                       `message_id` bigint(20) NOT NULL,
                       `user_id` bigint(20) NOT NULL,
                       `type` enum('mention','reply','reaction') NOT NULL,
                       PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=" . $wpdb->charset . ";",

                    "CREATE TABLE IF NOT EXISTS `" . bm_get_table('messages') ."` (
                      `id` bigint(20) NOT NULL AUTO_INCREMENT,
                      `thread_id` bigint(20) NOT NULL,
                      `sender_id` bigint(20) NOT NULL,
                      `message` longtext CHARACTER SET " . $wpdb->charset . " COLLATE " . $wpdb->collate . " NOT NULL,
                      `date_sent` datetime NOT NULL,
                      PRIMARY KEY (`id`),
                      KEY `sender_id` (`sender_id`),
                      KEY `thread_id` (`thread_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=" . $wpdb->charset . ";",

                    "CREATE TABLE IF NOT EXISTS `" . bm_get_table('meta') ."` (
                      `meta_id` bigint(20) NOT NULL AUTO_INCREMENT,
                      `bm_message_id` bigint(20) NOT NULL,
                      `meta_key` varchar(255) CHARACTER SET " . $wpdb->charset . " COLLATE " . $wpdb->collate . " DEFAULT NULL,
                      `meta_value` longtext CHARACTER SET " . $wpdb->charset . " COLLATE " . $wpdb->collate . ",
                      PRIMARY KEY (`meta_id`),
                      KEY `bm_message_id` (`bm_message_id`),
                      KEY `meta_key` (`meta_key`(191))
                    ) ENGINE=InnoDB DEFAULT CHARSET=" . $wpdb->charset . ";",

                    "CREATE TABLE IF NOT EXISTS `" . bm_get_table('recipients') ."` (
                      `id` bigint(20) NOT NULL AUTO_INCREMENT,
                      `user_id` bigint(20) NOT NULL,
                      `thread_id` bigint(20) NOT NULL,
                      `unread_count` int(10) NOT NULL DEFAULT '0',
                      `last_read` datetime NULL,
                      `last_delivered` datetime NULL,
                      `is_muted` tinyint(1) NOT NULL DEFAULT '0',
                      `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
                      `last_update` bigint(20) NOT NULL DEFAULT '0',
                      PRIMARY KEY (`id`),
                      UNIQUE KEY `user_thread` (`user_id`,`thread_id`),
                      KEY `user_id` (`user_id`),
                      KEY `thread_id` (`thread_id`),
                      KEY `is_deleted` (`is_deleted`),
                      KEY `unread_count` (`unread_count`),
                      KEY `last_read` (`last_read`),
                      KEY `last_delivered` (`last_delivered`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=" . $wpdb->charset . ";",

                    "CREATE TABLE IF NOT EXISTS `" . bm_get_table('threadsmeta') ."` (
                      `meta_id` bigint(20) NOT NULL AUTO_INCREMENT,
                      `bm_thread_id` bigint(20) NOT NULL,
                      `meta_key` varchar(255) CHARACTER SET " . $wpdb->charset . " DEFAULT NULL,
                      `meta_value` longtext CHARACTER SET " . $wpdb->charset . ",
                      PRIMARY KEY (`meta_id`),
                      KEY `meta_key` (`meta_key`(191)),
                      KEY `thread_id` (`bm_thread_id`) USING BTREE
                    ) ENGINE=InnoDB DEFAULT CHARSET=" . $wpdb->charset . ";",

                    "CREATE TABLE IF NOT EXISTS `" . bm_get_table('threads') ."` (
                      `id` bigint(20) NOT NULL AUTO_INCREMENT,
                      `subject` varchar(255) CHARACTER SET " . $wpdb->charset . " NOT NULL,
                      `type` enum('thread','group','chat-room') CHARACTER SET " . $wpdb->charset . " NOT NULL DEFAULT 'thread',
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=" . $wpdb->charset . ";",
                ]
            ];

            foreach( $sqls  as $version => $queries ){
                if( $version > $db_2_version ){
                    foreach ( $queries as $query ){
                        $sql[] = $query;
                    }
                }
            }

            if( count( $sql ) > 0 ) {
                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

                foreach( $sql as $query){
                    dbDelta($query);
                }

                update_option( 'better_messages_2_db_version', $this->db_version );
            }
        }

        public function migrations(){
            global $wpdb;

            $db_migrated = get_option('better_messages_db_migrated', false);

            if( ! $db_migrated ) {
                set_time_limit(0);
                require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

                $time = Better_Messages()->functions->get_microtime();

                $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . bm_get_table('messages') );

                if( $count === 0 ){
                    $wpdb->query("TRUNCATE " . bm_get_table('threads') . ";");
                    $wpdb->query("TRUNCATE " . bm_get_table('recipients') . ";");
                    $wpdb->query("TRUNCATE " . bm_get_table('messages') . ";");
                    $wpdb->query("TRUNCATE " . bm_get_table('threadsmeta') . ";");
                    $wpdb->query("TRUNCATE " . bm_get_table('meta') . ";");

                    $thread_ids = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT thread_id
                    FROM " . $wpdb->prefix . "bp_messages_recipients recipients
                    GROUP BY thread_id")));

                    foreach ($thread_ids as $thread_id) {
                        $type = $this->get_thread_type($thread_id);
                        $subject = Better_Messages()->functions->remove_re($wpdb->get_var($wpdb->prepare("SELECT subject
                        FROM {$wpdb->prefix}bp_messages_messages
                        WHERE thread_id = %d
                        ORDER BY date_sent DESC
                        LIMIT 0, 1", $thread_id)));

                        $wpdb->insert(bm_get_table('threads'), [
                            'id' => $thread_id,
                            'subject' => $subject,
                            'type' => $type
                        ]);
                    }

                    $wpdb->query($wpdb->prepare("INSERT IGNORE INTO " . bm_get_table('recipients') . "
                    (user_id,thread_id,unread_count,is_deleted, last_update, is_muted)
                    SELECT user_id, thread_id, unread_count, is_deleted, %d, 0
                    FROM " . $wpdb->prefix . "bp_messages_recipients", $time));

                    $wpdb->query("INSERT IGNORE INTO " . bm_get_table('messages') . "
                    (id,thread_id,sender_id,message,date_sent)
                    SELECT id,thread_id, sender_id, message, date_sent
                    FROM " . $wpdb->prefix . "bp_messages_messages
                    WHERE date_sent != '0000-00-00 00:00:00'");

                    $wpdb->query("INSERT IGNORE INTO " . bm_get_table('threadsmeta') . "
                    (bm_thread_id, meta_key, meta_value)
                    SELECT bpbm_threads_id, meta_key, meta_value
                    FROM " . $wpdb->prefix . "bpbm_threadsmeta");

                    $wpdb->query("INSERT IGNORE INTO " . bm_get_table('meta') . "
                    (bm_message_id, meta_key, meta_value)
                    SELECT message_id, meta_key, meta_value
                    FROM " . $wpdb->prefix . "bp_messages_meta");

                }

                update_option( 'better_messages_db_migrated', true );
            }
        }

        public function get_thread_type( $thread_id ){
            global $wpdb;

            if( Better_Messages()->settings['enableGroups'] === '1' ) {
                $group_id = $wpdb->get_var( $wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}bpbm_threadsmeta WHERE `bpbm_threads_id` = %d AND `meta_key` = 'group_id'", $thread_id ) );
                if ( !! $group_id && bp_is_active('groups') ) {
                    if (Better_Messages()->groups->is_group_messages_enabled($group_id) === 'enabled') {
                        return 'group';
                    }
                }
            }

            if( Better_Messages()->settings['PSenableGroups'] === '1' ) {
                $group_id = $wpdb->get_var( $wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}bpbm_threadsmeta WHERE `bpbm_threads_id` = %d AND `meta_key` = 'peepso_group_id'", $thread_id ) );

                if ( !! $group_id ){
                    return 'group';
                }
            }

            if( function_exists('UM') && Better_Messages()->settings['UMenableGroups'] === '1' ) {
                $group_id = $wpdb->get_var( $wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}bpbm_threadsmeta WHERE `bpbm_threads_id` = %d AND `meta_key` = 'um_group_id'", $thread_id ) );


                if ( !! $group_id ){
                    return 'group';
                }
            }

            $chat_id = $wpdb->get_var( $wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}bpbm_threadsmeta WHERE `bpbm_threads_id` = %d AND `meta_key` = 'chat_id'", $thread_id ) );

            if( ! empty( $chat_id ) ) {
                return 'chat-room';
            }

            return 'thread';
        }
    }


    function Better_Messages_Rest_Api_DB_Migrate(){
        return Better_Messages_Rest_Api_DB_Migrate::instance();
    }
endif;

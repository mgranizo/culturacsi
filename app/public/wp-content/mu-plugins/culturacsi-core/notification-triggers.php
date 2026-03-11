<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'culturacsi_register_notification_triggers' ) ) {
	/**
	 * Register custom Notification triggers for semantic moderation outcomes.
	 *
	 * @return void
	 */
	function culturacsi_register_notification_triggers() {
		if ( ! class_exists( '\BracketSpace\Notification\Register' ) ) {
			return;
		}

		if ( ! class_exists( '\BracketSpace\Notification\Repository\Trigger\BaseTrigger' ) ) {
			return;
		}

		if ( ! class_exists( '\BracketSpace\Notification\Repository\Trigger\User\UserTrigger' ) ) {
			return;
		}

		if ( ! class_exists( 'CulturacsiNotificationTriggerPostModerationBase', false ) ) {
			abstract class CulturacsiNotificationTriggerPostModerationBase extends \BracketSpace\Notification\Repository\Trigger\BaseTrigger {
				/**
				 * Moderated post.
				 *
				 * @var WP_Post
				 */
				public $post;

				/**
				 * Post author.
				 *
				 * @var WP_User|false
				 */
				public $authorUser;

				/**
				 * Moderating user.
				 *
				 * @var WP_User|false
				 */
				public $actorUser;

				/**
				 * Previous state payload.
				 *
				 * @var array<string, string>
				 */
				public $oldState = array();

				/**
				 * New state payload.
				 *
				 * @var array<string, string>
				 */
				public $newState = array();

				/**
				 * Semantic moderation action.
				 *
				 * @var string
				 */
				public $moderationAction = '';

				/**
				 * Internal recipient routing data.
				 *
				 * @var string
				 */
				public $recipientUserIds = '';

				/**
				 * Constructor.
				 *
				 * @param string $slug Trigger slug.
				 * @param string $name Trigger name.
				 * @param string $hook Source action hook.
				 */
				public function __construct( $slug, $name, $hook ) {
					parent::__construct( $slug, $name );
					$this->setGroup( 'CulturaCSI' );
					$this->addAction( $hook, 10, 5 );
				}

				/**
				 * Get the expected moderation action.
				 *
				 * @return string
				 */
				abstract protected function get_expected_action();

				/**
				 * Populate trigger context.
				 *
				 * @param WP_Post              $post              Target post.
				 * @param array<string,string> $old_state         Previous state.
				 * @param array<string,string> $new_state         New state.
				 * @param string               $moderation_action Semantic action.
				 * @param int                  $actor_user_id     Moderating user ID.
				 * @return bool|void
				 */
				public function context( $post, $old_state, $new_state, $moderation_action, $actor_user_id ) {
					if ( ! $post instanceof WP_Post ) {
						return false;
					}

					$moderation_action = sanitize_key( (string) $moderation_action );
					if ( $this->get_expected_action() !== $moderation_action ) {
						return false;
					}

					$this->post             = $post;
					$this->authorUser       = get_userdata( (int) $post->post_author );
					$this->actorUser        = get_userdata( (int) $actor_user_id );
					$this->oldState         = is_array( $old_state ) ? $old_state : array();
					$this->newState         = is_array( $new_state ) ? $new_state : array();
					$this->moderationAction = $moderation_action;
					$this->recipientUserIds = implode( ',', $this->resolve_recipient_user_ids( $post ) );
				}

				/**
				 * Register merge tags.
				 *
				 * @return void
				 */
				public function mergeTags() {
					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'content_ID',
								'name'        => 'Content ID',
								'group'       => 'Content',
								'description' => '123',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return (string) $trigger->post->ID;
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'content_type',
								'name'        => 'Content type',
								'group'       => 'Content',
								'description' => 'event',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return (string) $trigger->post->post_type;
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'content_title',
								'name'        => 'Content title',
								'group'       => 'Content',
								'description' => 'Sample title',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return html_entity_decode( get_the_title( $trigger->post ) );
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'content_permalink',
								'name'        => 'Content permalink',
								'group'       => 'Content',
								'description' => 'https://example.test/content/',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return get_permalink( $trigger->post );
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'content_author_user_ID',
								'name'        => 'Content author user ID',
								'group'       => 'Author',
								'description' => '123',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return ( $trigger->authorUser instanceof WP_User ) ? (string) $trigger->authorUser->ID : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'content_author_user_email',
								'name'        => 'Content author user email',
								'group'       => 'Author',
								'description' => 'author@example.test',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return ( $trigger->authorUser instanceof WP_User ) ? (string) $trigger->authorUser->user_email : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'content_author_user_display_name',
								'name'        => 'Content author display name',
								'group'       => 'Author',
								'description' => 'Jane Doe',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return ( $trigger->authorUser instanceof WP_User ) ? (string) $trigger->authorUser->display_name : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'old_post_status',
								'name'        => 'Old post status',
								'group'       => 'Moderation',
								'description' => 'pending',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return isset( $trigger->oldState['post_status'] ) ? (string) $trigger->oldState['post_status'] : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'new_post_status',
								'name'        => 'New post status',
								'group'       => 'Moderation',
								'description' => 'draft',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return isset( $trigger->newState['post_status'] ) ? (string) $trigger->newState['post_status'] : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'moderation_action',
								'name'        => 'Moderation action',
								'group'       => 'Moderation',
								'description' => 'reject',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return (string) $trigger->moderationAction;
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'actor_user_ID',
								'name'        => 'Actor user ID',
								'group'       => 'Actor',
								'description' => '2',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return ( $trigger->actorUser instanceof WP_User ) ? (string) $trigger->actorUser->ID : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'actor_user_login',
								'name'        => 'Actor user login',
								'group'       => 'Actor',
								'description' => 'mgranizo',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return ( $trigger->actorUser instanceof WP_User ) ? (string) $trigger->actorUser->user_login : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'actor_user_email',
								'name'        => 'Actor user email',
								'group'       => 'Actor',
								'description' => 'actor@example.test',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return ( $trigger->actorUser instanceof WP_User ) ? (string) $trigger->actorUser->user_email : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'actor_user_display_name',
								'name'        => 'Actor display name',
								'group'       => 'Actor',
								'description' => 'Mario Granizo',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return ( $trigger->actorUser instanceof WP_User ) ? (string) $trigger->actorUser->display_name : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'recipient_user_ids',
								'name'        => 'Recipient user IDs',
								'group'       => 'Routing',
								'description' => '12,34',
								'example'     => true,
								'hidden'      => true,
								'resolver'    => static function ( $trigger ) {
									return (string) $trigger->recipientUserIds;
								},
							)
						)
					);
				}

				/**
				 * Resolve association-linked recipients or fall back to the post author.
				 *
				 * @param WP_Post $post Target post.
				 * @return array<int, int>
				 */
				private function resolve_recipient_user_ids( WP_Post $post ) {
					$association_id = 0;

					if ( function_exists( 'culturacsi_portal_post_owner_association_id' ) ) {
						$association_id = (int) culturacsi_portal_post_owner_association_id( $post );
					} elseif ( 'association' === $post->post_type ) {
						$association_id = (int) $post->ID;
					} elseif ( in_array( $post->post_type, array( 'event', 'news', 'csi_content_entry' ), true ) ) {
						$association_id = (int) get_post_meta( (int) $post->ID, 'organizer_association_id', true );
					}

					$recipient_ids = array();
					if ( $association_id > 0 ) {
						$recipient_ids = get_users(
							array(
								'meta_key'   => 'association_post_id',
								'meta_value' => (string) $association_id,
								'fields'     => 'ID',
								'number'     => -1,
							)
						);

						$recipient_ids = array_values(
							array_filter(
								array_map( 'intval', $recipient_ids ),
								static function ( $user_id ) {
									return $user_id > 0 && ! user_can( $user_id, 'manage_options' );
								}
							)
						);
					}

					if ( empty( $recipient_ids ) ) {
						$post_author_id = (int) $post->post_author;
						if ( $post_author_id > 0 && ! user_can( $post_author_id, 'manage_options' ) ) {
							$recipient_ids[] = $post_author_id;
						}
					}

					$recipient_ids = array_values( array_unique( array_map( 'intval', $recipient_ids ) ) );
					return array_filter(
						$recipient_ids,
						static function ( $user_id ) {
							return $user_id > 0;
						}
					);
				}
			}
		}

		if ( ! class_exists( 'CulturacsiNotificationTriggerPostRejected', false ) ) {
			class CulturacsiNotificationTriggerPostRejected extends CulturacsiNotificationTriggerPostModerationBase {
				public function __construct() {
					parent::__construct( 'culturacsi/post/rejected', 'CulturaCSI content rejected', 'culturacsi_post_rejected' );
					$this->setDescription( 'Fires when reserved-area content is rejected.' );
				}

				protected function get_expected_action() {
					return 'reject';
				}
			}
		}

		if ( ! class_exists( 'CulturacsiNotificationTriggerPostHeld', false ) ) {
			class CulturacsiNotificationTriggerPostHeld extends CulturacsiNotificationTriggerPostModerationBase {
				public function __construct() {
					parent::__construct( 'culturacsi/post/held', 'CulturaCSI content held', 'culturacsi_post_held' );
					$this->setDescription( 'Fires when reserved-area content is placed on hold.' );
				}

				protected function get_expected_action() {
					return 'hold';
				}
			}
		}

		if ( ! class_exists( 'CulturacsiNotificationTriggerUserModerationBase', false ) ) {
			abstract class CulturacsiNotificationTriggerUserModerationBase extends \BracketSpace\Notification\Repository\Trigger\User\UserTrigger {
				/**
				 * Previous state payload.
				 *
				 * @var array<string, mixed>
				 */
				public $oldState = array();

				/**
				 * New state payload.
				 *
				 * @var array<string, mixed>
				 */
				public $newState = array();

				/**
				 * Semantic moderation action.
				 *
				 * @var string
				 */
				public $moderationAction = '';

				/**
				 * Moderating user.
				 *
				 * @var WP_User|false
				 */
				public $actorUser;

				/**
				 * Constructor.
				 *
				 * @param string $slug Trigger slug.
				 * @param string $name Trigger name.
				 * @param string $hook Source action hook.
				 */
				public function __construct( $slug, $name, $hook ) {
					parent::__construct( $slug, $name );
					$this->setGroup( 'CulturaCSI' );
					$this->addAction( $hook, 10, 5 );
				}

				/**
				 * Get the expected moderation action.
				 *
				 * @return string
				 */
				abstract protected function get_expected_action();

				/**
				 * Populate trigger context.
				 *
				 * @param WP_User             $user              Target user.
				 * @param array<string,mixed> $old_state         Previous state.
				 * @param array<string,mixed> $new_state         New state.
				 * @param string              $moderation_action Semantic action.
				 * @param int                 $actor_user_id     Moderating user ID.
				 * @return bool|void
				 */
				public function context( $user, $old_state, $new_state, $moderation_action, $actor_user_id ) {
					if ( ! $user instanceof WP_User ) {
						return false;
					}

					$moderation_action = sanitize_key( (string) $moderation_action );
					if ( $this->get_expected_action() !== $moderation_action ) {
						return false;
					}

					$this->userId           = (int) $user->ID;
					$this->userObject       = $user;
					$this->oldState         = is_array( $old_state ) ? $old_state : array();
					$this->newState         = is_array( $new_state ) ? $new_state : array();
					$this->moderationAction = $moderation_action;
					$this->actorUser        = get_userdata( (int) $actor_user_id );
				}

				/**
				 * Register merge tags.
				 *
				 * @return void
				 */
				public function mergeTags() {
					parent::mergeTags();

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'old_roles',
								'name'        => 'Old roles',
								'group'       => 'Moderation',
								'description' => 'association_pending',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									$roles = isset( $trigger->oldState['roles'] ) && is_array( $trigger->oldState['roles'] ) ? $trigger->oldState['roles'] : array();
									return implode( ', ', array_map( 'strval', $roles ) );
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'new_roles',
								'name'        => 'New roles',
								'group'       => 'Moderation',
								'description' => 'association_manager',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									$roles = isset( $trigger->newState['roles'] ) && is_array( $trigger->newState['roles'] ) ? $trigger->newState['roles'] : array();
									return implode( ', ', array_map( 'strval', $roles ) );
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'old_moderation_state',
								'name'        => 'Old moderation state',
								'group'       => 'Moderation',
								'description' => 'pending',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return isset( $trigger->oldState['moderation_state'] ) ? (string) $trigger->oldState['moderation_state'] : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'new_moderation_state',
								'name'        => 'New moderation state',
								'group'       => 'Moderation',
								'description' => 'approved',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return isset( $trigger->newState['moderation_state'] ) ? (string) $trigger->newState['moderation_state'] : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'old_pending_approval',
								'name'        => 'Old pending approval flag',
								'group'       => 'Moderation',
								'description' => '1',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return isset( $trigger->oldState['pending_approval'] ) ? (string) $trigger->oldState['pending_approval'] : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'new_pending_approval',
								'name'        => 'New pending approval flag',
								'group'       => 'Moderation',
								'description' => '',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return isset( $trigger->newState['pending_approval'] ) ? (string) $trigger->newState['pending_approval'] : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'moderation_action',
								'name'        => 'Moderation action',
								'group'       => 'Moderation',
								'description' => 'hold',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return (string) $trigger->moderationAction;
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'actor_user_ID',
								'name'        => 'Actor user ID',
								'group'       => 'Actor',
								'description' => '2',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return ( $trigger->actorUser instanceof WP_User ) ? (string) $trigger->actorUser->ID : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'actor_user_login',
								'name'        => 'Actor user login',
								'group'       => 'Actor',
								'description' => 'mgranizo',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return ( $trigger->actorUser instanceof WP_User ) ? (string) $trigger->actorUser->user_login : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'actor_user_email',
								'name'        => 'Actor user email',
								'group'       => 'Actor',
								'description' => 'actor@example.test',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return ( $trigger->actorUser instanceof WP_User ) ? (string) $trigger->actorUser->user_email : '';
								},
							)
						)
					);

					$this->addMergeTag(
						new \BracketSpace\Notification\Repository\MergeTag\StringTag(
							array(
								'slug'        => 'actor_user_display_name',
								'name'        => 'Actor display name',
								'group'       => 'Actor',
								'description' => 'Mario Granizo',
								'example'     => true,
								'resolver'    => static function ( $trigger ) {
									return ( $trigger->actorUser instanceof WP_User ) ? (string) $trigger->actorUser->display_name : '';
								},
							)
						)
					);
				}
			}
		}

		if ( ! class_exists( 'CulturacsiNotificationTriggerUserApproved', false ) ) {
			class CulturacsiNotificationTriggerUserApproved extends CulturacsiNotificationTriggerUserModerationBase {
				public function __construct() {
					parent::__construct( 'culturacsi/user/approved', 'CulturaCSI user approved', 'culturacsi_user_approved' );
					$this->setDescription( 'Fires when a reserved-area user is approved.' );
				}

				protected function get_expected_action() {
					return 'approve';
				}
			}
		}

		if ( ! class_exists( 'CulturacsiNotificationTriggerUserRejected', false ) ) {
			class CulturacsiNotificationTriggerUserRejected extends CulturacsiNotificationTriggerUserModerationBase {
				public function __construct() {
					parent::__construct( 'culturacsi/user/rejected', 'CulturaCSI user rejected', 'culturacsi_user_rejected' );
					$this->setDescription( 'Fires when a reserved-area user is rejected.' );
				}

				protected function get_expected_action() {
					return 'reject';
				}
			}
		}

		if ( ! class_exists( 'CulturacsiNotificationTriggerUserHeld', false ) ) {
			class CulturacsiNotificationTriggerUserHeld extends CulturacsiNotificationTriggerUserModerationBase {
				public function __construct() {
					parent::__construct( 'culturacsi/user/held', 'CulturaCSI user held', 'culturacsi_user_held' );
					$this->setDescription( 'Fires when a reserved-area user is put on hold.' );
				}

				protected function get_expected_action() {
					return 'hold';
				}
			}
		}

		\BracketSpace\Notification\Register::trigger( new CulturacsiNotificationTriggerPostRejected() );
		\BracketSpace\Notification\Register::trigger( new CulturacsiNotificationTriggerPostHeld() );
		\BracketSpace\Notification\Register::trigger( new CulturacsiNotificationTriggerUserApproved() );
		\BracketSpace\Notification\Register::trigger( new CulturacsiNotificationTriggerUserRejected() );
		\BracketSpace\Notification\Register::trigger( new CulturacsiNotificationTriggerUserHeld() );
	}

	add_action( 'notification/init', 'culturacsi_register_notification_triggers', 20 );
}

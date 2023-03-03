<?php

if (! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class SamedayCourierPickupPoints extends WP_List_Table
{
	/** Class constructor */
	public function __construct()
	{
		parent::__construct( [
			'singular' => __('Pickup-point', 'samedaycourier'),
			'plural'   => __('Pickup-points', 'samedaycourier'),
			'ajax'     => false
		] );
	}

	private const ACCEPTED_FILTERS = [
		'sameday_id'
	];

	private const GRID_PER_PAGE_VALUE = 10;

	/**
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return array|object|stdClass[]|null
	 */
	public static function get_pickup_points(
		int $per_page = self::GRID_PER_PAGE_VALUE,
		int $page_number = 1
	)
	{

		global $wpdb;

		$table = "{$wpdb->prefix}sameday_pickup_point";
		$is_testing = SamedayCourierHelperClass::isTesting();

		$sql = SamedayCourierHelperClass::buildGridQuery(
			$table,
			$is_testing,
			self::ACCEPTED_FILTERS,
			$per_page,
			$page_number
		);

		return $wpdb->get_results($sql, 'ARRAY_A');
	}

	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public static function record_count(): ?string
	{
		global $wpdb;

		$table = "{$wpdb->prefix}sameday_pickup_point";
		$is_testing = SamedayCourierHelperClass::isTesting();

		$sql = sprintf(
			"SELECT COUNT(*) FROM %s WHERE is_testing='%s'",
			$table,
			$is_testing
		);

		return $wpdb->get_var($sql);
	}

	/** Text displayed when no pickup-points data is available */
	public function no_items(): void
	{
		__( 'No pickup-points avaliable.','samedaycourier');
	}

	/**
	 * Render a column when no column specific method exist.
	 *
	 * @param array $item
	 * @param string $column_name
	 *
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ($column_name) {
			case 'contactPersons':
				return $this->parseContactPersons(unserialize($item[$column_name], ['']));
			case 'default_pickup_point':
				return $item[$column_name] ? "<strong>Yes</strong>" : "No";
			default:
				return $item[$column_name];
		}
	}

	/**
	 * @param $contactPersons
	 *
	 * @return string
	 */
	private function  parseContactPersons($contactPersons): string
	{
		$persons = array();
		foreach ($contactPersons as $contact_person) {
			$persons[] = "<strong>{$contact_person->getName()}</strong> <br/> {$contact_person->getPhone()}";
		}

		return implode(',', $persons);
	}

	/**
	 *  Associative array of columns
	 *
	 * @return array
	 */
	public function get_columns(): array
	{
		return [
			'sameday_id' => __('Sameday ID', 'samedaycourier'),
			'sameday_alias' => __('Name', 'samedaycourier'),
			'city' => __('City', 'samedaycourier'),
			'county' => __('County', 'samedaycourier'),
			'address' => __('Address', 'samedaycourier'),
			'contactPersons' => __('Contact Persons', 'samedaycourier'),
			'default_pickup_point' => __('Is default ', 'samedaycourier'),
		];
	}

	/**
	 * Columns to make sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns(): array
	{
		return array(
			'sameday_id' => array('sameday_id', true)
		);
	}

	/**
	 * Handles data query and filter, sorting, and pagination.
	 */
	public function prepare_items(): void
	{
		$this->_column_headers = $this->get_column_info();

		$per_page     = $this->get_items_per_page( 'pickup-points_per_page', self::GRID_PER_PAGE_VALUE);
		$current_page = $this->get_pagenum();
		$total_items  = self::record_count();

		$this->set_pagination_args( [
			'total_items' => $total_items, //WE have to calculate the total number of items
			'per_page'    => $per_page //WE have to determine how many items to show on a page
		] );

		$this->items = self::get_pickup_points($per_page, $current_page);
	}
}


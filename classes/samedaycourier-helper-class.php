<?php

if (! defined( 'ABSPATH' ) ) {
	exit;
}

class HelperClass
{
	public static function refreshServices()
	{
		$samedayOption = get_option('woocommerce_samedaycourier_settings');
		if ( !isset($samedayOption) && empty($samedayOption) ) {
			wp_redirect(admin_url() . 'edit.php?post_type=page&page=sameday_services');
		}

		$is_testing = $samedayOption['is_testing'] === 'yes' ? 1 : 0;

		$sameday = new \Sameday\Sameday(Api::initClient(
			$samedayOption['user'],
			$samedayOption['password'],
			$is_testing
		));

		$remoteServices = [];
		$page = 1;

		do {
			$request = new \Sameday\Requests\SamedayGetServicesRequest();
			$request->setPage($page++);

			try {
				$services = $sameday->getServices($request);
			} catch (\Sameday\Exceptions\SamedayAuthenticationException $e) {
				wp_redirect(admin_url() . 'edit.php?post_type=page&page=sameday_services');
			}

			foreach ($services->getServices() as $serviceObject) {
				$service = getServiceSameday($serviceObject->getId(), $is_testing);
				if (! $service) {
					// Service not found, add it.
					addService($serviceObject, $is_testing);
				}

				// Save as current sameday service.
				$remoteServices[] = $serviceObject->getId();
			}

		} while ($page <= $services->getPages());

		// Build array of local services.
		$localServices = array_map(
			function ($service) {
				return array(
					'id' => $service->id,
					'sameday_id' => $service->sameday_id
				);
			},
			getServices($is_testing)
		);

		// Delete local services that aren't present in remote services anymore.
		foreach ($localServices as $localService) {
			if (!in_array($localService['sameday_id'], $remoteServices)) {
				deleteService($localService['id']);
			}
		}

		wp_redirect(admin_url() . 'edit.php?post_type=page&page=sameday_services');
	}

	public static function refreshPickupPoints()
	{
		$samedayOption = get_option('woocommerce_samedaycourier_settings');
		if ( !isset($samedayOption) && empty($samedayOption) ) {
			wp_redirect(admin_url() . 'admin.php?page=sameday_pickup_points');
		}

		$is_testing = $samedayOption['is_testing'] === 'yes' ? 1 : 0;

		$sameday = new \Sameday\Sameday(Api::initClient(
			$samedayOption['user'],
			$samedayOption['password'],
			$is_testing
		));

		$remotePickupPoints = [];
		$page = 1;
		do {
			$request = new Sameday\Requests\SamedayGetPickupPointsRequest();
			$request->setPage($page++);
			try {
				$pickUpPoints = $sameday->getPickupPoints($request);
			} catch (\Sameday\Exceptions\SamedayAuthenticationException $e) {
				wp_redirect(admin_url() . 'admin.php?page=sameday_pickup_points');
			}

			foreach ($pickUpPoints->getPickupPoints() as $pickupPointObject) {
				$pickupPoint = getPickupPointSameday($pickupPointObject->getId(), $is_testing);
				if (!$pickupPoint) {
					// Pickup point not found, add it.
					addPickupPoint($pickupPointObject, $is_testing);
				} else {
					updatePickupPoint($pickupPointObject, $is_testing);
				}

				// Save as current pickup points.
				$remotePickupPoints[] = $pickupPointObject->getId();
			}
		} while ($page <= $pickUpPoints->getPages());

		// Build array of local pickup points.
		$localPickupPoints = array_map(
			function ($pickupPoint) {
				return array(
					'id' => $pickupPoint->id,
					'sameday_id' => $pickupPoint->sameday_id
				);
			},
			getPickupPoints($is_testing)
		);

		// Delete local pickup points that aren't present in remote pickup points anymore.
		foreach ($localPickupPoints as $localPickupPoint) {
			if (!in_array($localPickupPoint['sameday_id'], $remotePickupPoints)) {
				deletePickupPoint($localPickupPoint['id']);
			}
		}

		wp_redirect(admin_url() . 'edit.php?post_type=page&page=sameday_pickup_points');
	}

	public static function editService()
	{
		if (! $_POST['action'] === 'edit_service') {
			return wp_redirect(admin_url() . 'edit.php?post_type=page&page=sameday_services');
		}

		$post_fields = array(
			'id' => array(
				'required' => true,
				'value' => $_POST['samedaycourier-service-id']
			),
			'name' => array(
				'required' => true,
				'value' =>  $_POST['samedaycourier-service-name']
			),
			'price' => array(
				'required' => true,
				'value' => $_POST['samedaycourier-price']
			),
			'price_free' => array(
				'required' => false,
				'value' => $_POST['samedaycourier-free-delivery-price'] ?: null
			),
			'status' => array(
				'required' => false,
				'value' => $_POST['samedaycourier-status']
			)
		);

		$errors = array();
		foreach ($post_fields as $field => $field_value) {
			if ($field_value['required'] && !strlen($field_value['value'])) {
				$errors[] = "The {$field} must not be empty";
			}
		}

		if (empty($errors)) {
			$service = array(
				'id' => (int) $post_fields['id']['value'],
				'name' => $post_fields['name']['value'],
				'price' => $post_fields['price']['value'],
				'price_free' => $post_fields['price_free']['value'],
				'status' => $post_fields['status']['value']
			);

			updateService($service);
		}

		wp_redirect(admin_url() . 'edit.php?post_type=page&page=sameday_services&action=edit&id=' . $post_fields['id']['value']);
	}
}
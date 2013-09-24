<?php

class user {

	public static function whirlpool($hash) {
		$out = "";
		for ($i = 0; $i < 1000; $i++) {
			$out = hash('whirlpool', $out . $hash);
		}
		return $out;
	}

	public static function login($user, $pass) {
		$row = query(
			'lvf_users',
			'findOne',
			array(
				array(
					'user' => strtolower($user)
				),
				array(
					'_id' => 0,
					'salt' => 1,
					'hash' => 1
				)
			)
		);
		if (!is_null($row)) {
			$hash = self::whirlpool($row['salt'] . $pass);
			if ($hash == $row['hash']) {
				$_SESSION['user'] = $user;
				$_SESSION['hash'] = $row['hash'];
				return true;
			}
		}
		return false;
	}

	public static function checkSession() {
		if (isset($_SESSION['user'])) {
			$row = query(
				'lvf_users',
				'findOne',
				array(
					array(
						'user' => strtolower($_SESSION['user']),
						'hash' => $_SESSION['hash']
					),
					array(
						'_id' => 1
					)
				)
			);
			return !is_null($row);
		}
		return false;
	}

}

?>
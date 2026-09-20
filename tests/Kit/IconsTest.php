<?php
declare( strict_types = 1 );

namespace MHMUiCore\Tests\Kit;

use MHMUiCore\Kit\Icons;
use PHPUnit\Framework\TestCase;

final class IconsTest extends TestCase {

	protected function setUp(): void {
		Icons::reset();
	}

	protected function tearDown(): void {
		Icons::reset();
	}

	public function test_a_seed_concept_resolves_to_its_dashicon_suffix(): void {
		self::assertSame( 'money-alt', Icons::resolve( 'revenue' ) );
		self::assertSame( 'calendar-alt', Icons::resolve( 'time' ) );
		self::assertSame( 'location-alt', Icons::resolve( 'place' ) );
	}

	public function test_a_raw_suffix_passes_through_unchanged(): void {
		self::assertSame( 'money-alt', Icons::resolve( 'money-alt' ) );
		self::assertSame( 'fuel', Icons::resolve( 'fuel' ) );
	}

	public function test_the_retired_key_location_is_NOT_a_concept(): void {
		// K5: 'location' gercek bir Dashicon adidir. Kavram olsaydi, bugun
		// dashicons-location basan her cagri yeri 0.14'te sessizce
		// dashicons-location-alt gosterirdi -- iki bagimsiz denetimin de
		// bloke edici saydigi kirilma.
		self::assertSame( 'location', Icons::resolve( 'location' ) );
	}

	public function test_an_empty_value_stays_empty(): void {
		self::assertSame( '', Icons::resolve( '' ) );
	}

	public function test_a_registered_concept_resolves_and_wins_over_the_seed(): void {
		Icons::register( array( 'vehicles' => 'car', 'revenue' => 'chart-pie' ) );

		self::assertSame( 'car', Icons::resolve( 'vehicles' ) );
		self::assertSame( 'chart-pie', Icons::resolve( 'revenue' ) );
	}

	public function test_the_last_registration_of_a_concept_wins(): void {
		Icons::register( array( 'vehicles' => 'car' ) );
		Icons::register( array( 'vehicles' => 'admin-site' ) );

		self::assertSame( 'admin-site', Icons::resolve( 'vehicles' ) );
	}

	public function test_a_malformed_entry_never_becomes_a_concept(): void {
		Icons::register( array( 'ok' => '', 'arr' => array( 'car' ), '' => 'car' ) );

		self::assertSame( 'ok', Icons::resolve( 'ok' ) );
		self::assertArrayNotHasKey( 'ok', Icons::map() );
		self::assertArrayNotHasKey( 'arr', Icons::map() );
	}

	public function test_EMPTY_SET_guard_the_seed_table_is_not_empty(): void {
		// Sozluk bosalirsa her "ham sonek" testi yesil kalir ve hicbir sey
		// olculmemis olur.
		self::assertGreaterThanOrEqual( 12, count( Icons::map() ) );
		self::assertSame( 'money-alt', Icons::map()['revenue'] ?? null );
	}
}

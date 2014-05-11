<?php

namespace Etu\Core\UserBundle\Test\Controller;

use Etu\Core\CoreBundle\Framework\Tests\MockUser;
use Etu\Core\UserBundle\Security\Authentication\UserToken;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileControllerTest extends WebTestCase
{
	public function testRestrictionProfile()
	{
		$client = static::createClient();

		$client->request('GET', '/user/profile');
		$this->assertEquals($client->getResponse()->getStatusCode(), 302);
	}

	public function testRestrictionProfileEdit()
	{
		$client = static::createClient();

		$client->request('GET', '/user/profile/edit');
		$this->assertEquals($client->getResponse()->getStatusCode(), 302);
	}

	public function testRestrictionProfileAvatar()
	{
		$client = static::createClient();

		$client->request('GET', '/user/profile/avatar');
		$this->assertEquals($client->getResponse()->getStatusCode(), 302);
	}

	public function testRestrictionTrombiEdit()
	{
		$client = static::createClient();

		$client->request('GET', '/user/trombi/edit');
		$this->assertEquals($client->getResponse()->getStatusCode(), 302);
	}

	public function testRestrictionView()
	{
		$client = static::createClient();

		$client->request('GET', '/user/admin');
		$this->assertEquals($client->getResponse()->getStatusCode(), 302);
	}

	public function testProfile()
	{
		$client = static::createClient();
		$client->getContainer()->get('security.context')->setToken(new UserToken(MockUser::createUser()));

		$crawler = $client->request('GET', '/user/profile');

		$this->assertGreaterThan(0, $crawler->filter('h2:contains("Mon profil")')->count());
	}

	public function testProfileEdit()
	{
		$client = static::createClient();
		$client->getContainer()->get('security.context')->setToken(new UserToken(MockUser::createUser()));

		$crawler = $client->request('GET', '/user/profile/edit');
		$this->assertGreaterThan(0, $crawler->filter('h2:contains("Modifier mes informations")')->count());
	}

	public function testTrombiEdit()
	{
		$client = static::createClient();
		$client->getContainer()->get('security.context')->setToken(new UserToken(MockUser::createUser()));

		$crawler = $client->request('GET', '/user/trombi/edit');
		$this->assertGreaterThan(0, $crawler->filter('h2:contains("Mon trombinoscope")')->count());
	}

	public function testView()
	{
		$client = static::createClient();
		$client->getContainer()->get('security.context')->setToken(new UserToken(MockUser::createUser()));

		$crawler = $client->request('GET', '/user/admin');
		$this->assertGreaterThan(0, $crawler->filter('h2:contains("Détail d\'un profil")')->count());
	}
}
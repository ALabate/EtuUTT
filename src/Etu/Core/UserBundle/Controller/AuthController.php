<?php

namespace Etu\Core\UserBundle\Controller;

use Etu\Core\UserBundle\Ldap\LdapManager;
use Etu\Core\UserBundle\Sync\Iterator\Element\ElementToImport;
use Etu\Core\CoreBundle\Framework\Definition\Controller;
use Etu\Core\UserBundle\Ldap\Model\User;
use Etu\Core\UserBundle\Ldap\Model\Organization;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class AuthController extends Controller
{
	/**
	 * Connect the user or the organization automatically if possible,
	 * ask for method to connect otherwise.
	 *
	 * @Route("/user", name="user_connect")
	 * @Template()
	 */
	public function connectAction()
	{
		if ($this->getUserLayer()->isConnected()) {
			return $this->redirect($this->generateUrl('homepage'));
		}

		if ($this->getKernel()->getEnvironment() != 'test') {
			$this->initializeCAS();
			\phpCAS::setNoCasServerValidation();

			if (\phpCAS::isAuthenticated()) {
				// Try to connect user automatically
				$login = \phpCAS::getUser();

				$em = $this->getDoctrine()->getManager();

				$user = $em->getRepository('EtuUserBundle:User')->findOneBy(array('login' => $login));

				// If the user can't be loaded from database, we try for an organization
				if (! $user) {
					$orga = $em->getRepository('EtuUserBundle:Organization')->findOneBy(array('login' => $login, 'deleted' => false));

					if ($orga) {
						$user = $orga;
					}
				}

				// If the user can't be loaded even as organization, we try using LDAP
				if (! $user) {
					/** @var $ldap LdapManager */
					$ldap = $this->get('etu.user.ldap');

					$ldapUser = $ldap->getUser($login);

					// If we can't use a classic user, try with an organization
					if (! $ldapUser) {
						$ldapUser = $ldap->getOrga($login);
					}

					// We caught a user that is not in the database : we import it !
					if ($ldapUser instanceof User) {
						$import = new ElementToImport($this->getDoctrine(), $ldapUser);
						$user = $import->import(true);
					} elseif ($ldapUser instanceof Organization) {
						$this->get('session')->getFlashBag()->set('message', array(
							'type' => 'error',
							'message' => 'user.auth.connect.orga_exists_ldap'
						));

						return $this->redirect($this->generateUrl('homepage'));
					}
				}

				if ($user instanceof \Etu\Core\UserBundle\Entity\User && ! $user->getIsDeleted()) {
					$this->get('session')->set('user', $user->getId());
					$this->get('session')->getFlashBag()->set('message', array(
						'type' => 'success',
						'message' => 'user.auth.connect.confirm'
					));

					if (in_array($user->getLanguage(), $this->container->getParameter('etu.translation.languages'))) {
						$this->get('session')->set('_locale', $user->getLanguage());
					}

					return $this->redirect($this->generateUrl('homepage'));
				} elseif ($user instanceof Organization) {
					$this->get('session')->set('orga', $user->getId());
					$this->get('session')->getFlashBag()->set('message', array(
						'type' => 'success',
						'message' => 'user.auth.connect.confirm'
					));

					return $this->redirect($this->generateUrl('homepage'));
				}
			}
		}

		// If we can't, we ask for the method
		return array();
	}

	/**
	 * @Route("/user/cas", name="user_connect_cas")
	 */
	public function connectCasAction()
	{
		if ($this->getUserLayer()->isConnected()) {
			return $this->redirect($this->generateUrl('homepage'));
		}

		// Catch the CAS ticket to connect emails
		$ticket = $this->getRequest()->get('ticket', false);

		if (! empty($ticket) && is_string($ticket)) {
			$this->get('session')->set('ticket', $ticket);
		}

		// Otherwise, load phpCAS
		$this->initializeCAS();
		\phpCAS::setNoCasServerValidation();
		\phpCAS::forceAuthentication();

		// Try to connect user
		$login = \phpCAS::getUser();

		$em = $this->getDoctrine()->getManager();

		$user = $em->getRepository('EtuUserBundle:User')->findOneBy(array('login' => $login));

		// If the user can't be loaded from database, we try for an organization
		if (! $user) {
			$orga = $em->getRepository('EtuUserBundle:Organization')->findOneBy(array('login' => $login, 'deleted' => false));

			if ($orga) {
				$user = $orga;
			}
		}

		// If the user can't be loaded even as organization, we try using LDAP
		if (! $user) {
			/** @var $ldap LdapManager */
			$ldap = $this->get('etu.user.ldap');

			$ldapUser = $ldap->getUser($login);

			// If we can't use a classic user, try with an organization
			if (! $ldapUser) {
				$ldapUser = $ldap->getOrga($login);
			}

			// We caught a user that is not in the database : we import it !
			if ($ldapUser instanceof User) {
				$import = new ElementToImport($this->getDoctrine(), $ldapUser);
				$user = $import->import(true);
			} elseif ($ldapUser instanceof Organization) {
				$this->get('session')->getFlashBag()->set('message', array(
					'type' => 'success',
					'message' => 'user.auth.connect.orga_exists_ldap'
				));

				return $this->redirect($this->generateUrl('homepage'));
			} else {
				$this->get('session')->getFlashBag()->set('message', array(
					'type' => 'error',
					'message' => 'user.auth.connect.error'
				));

				return $this->redirect($this->generateUrl('user_connect'));
			}
		}

		if ($user instanceof \Etu\Core\UserBundle\Entity\User && ! $user->getIsDeleted()) {
			$this->get('session')->set('user', $user->getId());
			$this->get('session')->getFlashBag()->set('message', array(
				'type' => 'success',
				'message' => 'user.auth.connect.confirm'
			));

			if (in_array($user->getLanguage(), $this->container->getParameter('etu.translation.languages'))) {
				$this->get('session')->set('_locale', $user->getLanguage());
			}
		} elseif ($user instanceof \Etu\Core\UserBundle\Entity\Organization) {
			$this->get('session')->set('orga', $user->getId());
			$this->get('session')->getFlashBag()->set('message', array(
				'type' => 'success',
				'message' => 'user.auth.connect.confirm'
			));
		} else {
			$this->get('session')->getFlashBag()->set('message', array(
				'type' => 'success',
				'message' => 'user.auth.connect.error'
			));

			return $this->redirect($this->generateUrl('user_connect'));
		}

		return $this->redirect($this->generateUrl('homepage'));
	}

	/**
	 * @Route("/user/external", name="user_connect_external")
	 * @Template()
	 */
	public function connectExternalAction()
	{
		if ($this->getUserLayer()->isConnected()) {
			return $this->redirect($this->generateUrl('homepage'));
		}

		$em = $this->getDoctrine()->getManager();

		$user = new \Etu\Core\UserBundle\Entity\User();

		$form = $this->createFormBuilder($user)
			->add('login')
			->add('password', 'password')
			->getForm();

		$request = $this->getRequest();

		if ($request->getMethod() == 'POST' && $form->bind($request)->isValid()) {
			$result = $em->getRepository('EtuUserBundle:User')->findOneBy(array(
				'login' => $user->getLogin(),
				'password' => $this->get('etu.user.crypting')->encrypt($user->getPassword()),
				'isDeleted' => false,
			));

			if ($result) {
				$this->get('session')->set('user', $result->getId());
				$this->get('session')->getFlashBag()->set('message', array(
					'type' => 'success',
					'message' => 'user.auth.connect.confirm'
				));

				if (in_array($user->getLanguage(), $this->container->getParameter('etu.translation.languages'))) {
					$this->get('session')->set('_locale', $user->getLanguage());
				}

				return $this->redirect($this->generateUrl('homepage'));
			} else {
				$this->get('session')->getFlashBag()->set('message', array(
					'type' => 'error',
					'message' => 'user.auth.connect.error'
				));

				return $this->redirect($this->generateUrl('user_connect_external'));
			}
		}

		return array(
			'form' => $form->createView()
		);
	}

	/**
	 * @Route("/user/disconnect", name="user_disconnect")
	 */
	public function disconnectAction()
	{
		if (! $this->getUserLayer()->isConnected()) {
			return $this->redirect($this->generateUrl('homepage'));
		}

		$this->get('session')->set('orga', null);
		$this->get('session')->set('user', null);
		$this->get('session')->clear();
		$this->get('session')->getFlashBag()->set('message', array(
			'type' => 'success',
			'message' => 'user.auth.disconnect.confirm'
		));

		$this->initializeCAS();
		\phpCAS::setNoCasServerValidation();
		// \phpCAS::logoutWithRedirectService('http://'.$this->container->getParameter('etu.domain'));
		\phpCAS::logout();

		return $this->redirect($this->generateUrl('homepage'));
	}

	/**
	 * Initialize the CAS connection
	 */
	private function initializeCAS()
	{
		/*
		 * Redirect to CAS server if not logged in
		 * Get the username, find the infos in the database
		 * If not present, use LDAP
		 */
		require __DIR__.'/../Resources/lib/phpCAS/CAS.php';

		\phpCAS::client(
			$this->container->getParameter('etu.cas.version'),
			$this->container->getParameter('etu.cas.host'),
			$this->container->getParameter('etu.cas.port'),
			$this->container->getParameter('etu.cas.path'),
			$this->container->getParameter('etu.cas.change_session_id')
		);

		\phpCAS::setDebug(__DIR__.'/../Resources/temp/logs.txt');
	}
}

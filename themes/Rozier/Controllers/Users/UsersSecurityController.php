<?php
/**
 * Copyright © 2015, Ambroise Maupate and Julien Blanchet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * Except as contained in this notice, the name of the ROADIZ shall not
 * be used in advertising or otherwise to promote the sale, use or other dealings
 * in this Software without prior written authorization from Ambroise Maupate and Julien Blanchet.
 *
 * @file UsersSecurityController.php
 * @author Ambroise Maupate
 */
namespace Themes\Rozier\Controllers\Users;

use RZ\Roadiz\Core\Entities\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Themes\Rozier\RozierApp;

/**
 * Provide user security views and forms.
 */
class UsersSecurityController extends RozierApp
{
    /**
     * @param Symfony\Component\HttpFoundation\Request $request
     * @param int                                      $userId
     *
     * @return Symfony\Component\HttpFoundation\Response
     */
    public function securityAction(Request $request, $userId)
    {
        // Only user managers can review security
        $this->validateAccessForRole('ROLE_ACCESS_USERS');

        $user = $this->getService('em')
                     ->find('RZ\Roadiz\Core\Entities\User', (int) $userId);

        if ($user !== null) {
            $this->assignation['user'] = $user;
            $form = $this->buildEditSecurityForm($user);

            $form->handleRequest();

            if ($form->isValid()) {
                $this->editUserSecurity($form->getData(), $user, $request);
                $msg = $this->getTranslator()->trans(
                    'user.%name%.security.updated',
                    ['%name%' => $user->getUsername()]
                );

                $this->publishConfirmMessage($request, $msg);

                /*
                 * Force redirect to avoid resending form when refreshing page
                 */
                $response = new RedirectResponse(
                    $this->getService('urlGenerator')->generate(
                        'usersSecurityPage',
                        ['userId' => $user->getId()]
                    )
                );
                $response->prepare($request);

                return $response->send();
            }

            $this->assignation['form'] = $form->createView();

            return $this->render('users/security.html.twig', $this->assignation);
        } else {
            return $this->throw404();
        }
    }

    /**
     *
     * @param  User   $user
     *
     * @return \Symfony\Component\Form\Form
     */
    protected function buildEditSecurityForm(User $user)
    {
        $defaults = [
            'enabled' => $user->isEnabled(),
            'locked' => !$user->isAccountNonLocked(),
            'expiresAt' => $user->getExpiresAt(),
            'expired' => $user->getExpired(),
            'credentialsExpiresAt' => $user->getCredentialsExpiresAt(),
            'credentialsExpired' => $user->getCredentialsExpired(),
            'chroot' => ($user->getChroot() !== null) ? $user->getChroot()->getId() : null,
        ];

        $builder = $this->getService('formFactory')
                        ->createNamedBuilder('source', 'form', $defaults);

        $builder->add('enabled', 'checkbox', [
                    'label' => $this->getTranslator()->trans('user.enabled'),
                    'required' => false,
                ])
                ->add('locked', 'checkbox', [
                    'label' => $this->getTranslator()->trans('user.locked'),
                    'required' => false,
                ])
                ->add('expiresAt', 'datetime', [
                    'label' => $this->getTranslator()->trans('user.expiresAt'),
                    'required' => false,
                    'years' => range(date('Y'), date('Y') + 2),
                    'empty_value' => [
                        'year' => $this->getTranslator()->trans('year'),
                        'month' => $this->getTranslator()->trans('month'),
                        'day' => $this->getTranslator()->trans('day'),
                        'hour' => $this->getTranslator()->trans('hour'),
                        'minute' => $this->getTranslator()->trans('minute'),
                    ],
                ])
                ->add('expired', 'checkbox', [
                    'label' => $this->getTranslator()->trans('user.force.expired'),
                    'required' => false,
                ])
                ->add('credentialsExpiresAt', 'datetime', [
                    'label' => $this->getTranslator()->trans('user.credentialsExpiresAt'),
                    'required' => false,
                    'years' => range(date('Y'), date('Y') + 2),
                    'empty_value' => [
                        'year' => $this->getTranslator()->trans('year'),
                        'month' => $this->getTranslator()->trans('month'),
                        'day' => $this->getTranslator()->trans('day'),
                        'hour' => $this->getTranslator()->trans('hour'),
                        'minute' => $this->getTranslator()->trans('minute'),
                    ],
                ])
                ->add('credentialsExpired', 'checkbox', [
                    'label' => $this->getTranslator()->trans('user.force.credentialsExpired'),
                    'required' => false,
                ]);

        if ($this->getService('securityContext')->isGranted("ROLE_SUPERADMIN")) {
            $n = $user->getChroot();
            $n = ($n !== null) ? [$n] : [];
            $builder->add('chroot', new \RZ\Roadiz\CMS\Forms\NodesType($n), [
                'label' => $this->getTranslator()->trans('chroot'),
                'required' => false,
            ]);
        }

        return $builder->getForm();
    }

    protected function editUserSecurity(array $data, User $user, Request $request)
    {
        foreach ($data as $key => $value) {
            $setter = 'set' . ucwords($key);
            if ($key == "chroot") {
                if (count($value) > 1) {
                    $msg = $this->getTranslator()->trans('chroot.limited.one');
                    $this->publishErrorMessage($request, $msg);
                }
                if ($value !== null) {
                    $n = $this->getService('em')->find("RZ\Roadiz\Core\Entities\Node", $value[0]);
                    $user->$setter($n);
                } else {
                    $user->$setter(null);
                }
            } else {
                $user->$setter($value);
            }
        }

        $this->getService('em')->flush();
    }
}

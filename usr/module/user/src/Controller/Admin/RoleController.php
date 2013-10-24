<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

namespace Module\User\Controller\Admin;

use Pi;
use Pi\Mvc\Controller\ActionController;
use Pi\Paginator\Paginator;
use Module\User\Form\RoleForm;
use Module\User\Form\RoleFilter;

/**
 * Role controller
 *
 * Feature list:
 *
 *  1. List of roles with inheritance
 *  2. User list of a role
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
class RoleController extends ActionController
{
    /**
     * Columns for role model
     *
     * @var string[]
     */
    protected $roleColumns = array(
        'id', 'section', 'custom', 'active', 'name', 'title'
    );

    /**
     * Get role model
     *
     * @return Pi\Application\Model\Model
     */
    protected function model()
    {
        return Pi::model('role');
    }

    /**
     * Get role list
     *
     * Data structure
     *
     *  - role
     *    - id
     *    - name
     *    - title
     *    - active
     *    - custom
     *    - section
     *
     * @param string $section
     * @return array
     */
    protected function getRoles($section = '')
    {
        $roles = array();

        $select = $this->model()->select();
        $select->order('title ASC');
        if ($section) {
            $select->where(array('section' => $section));
        }
        $rowset = $this->model()->selectWith($select);
        foreach ($rowset as $row) {
            $role = $row->toArray();
            $role['active'] = (int) $role['active'];
            $role['custom'] = (int) $role['custom'];
            $roles[$row['name']] =$role;
        }

        return $roles;
    }

    /**
     * Entrance template
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view()->setTemplate('role');
    }

    /**
     * List of roles
     */
    public function listAction()
    {
        $roles = $this->getRoles();
        if (isset($roles['guest'])) {
            unset($roles['guest']);
        }
        $rowset = Pi::model('user_role')->count(
            array('role' => array_keys($roles)),
            'role'
        );
        $count = array();
        foreach ($rowset as $row) {
            $count[$row['role']] = (int) $row['count'];
        }

        $frontRoles = array();
        $adminRoles = array();
        foreach ($roles as $role) {
            $role['count'] = isset($count[$role['name']])
                ? (int) $count[$role['name']] : 0;
            if ('admin' == $role['section']) {
                $adminRoles[] = $role;
            } else {
                $frontRoles[] = $role;
            }
        }

        return array(
            'frontRoles'    => $frontRoles,
            'adminRoles'    => $adminRoles,
        );
    }

    /**
     * Add a custom role
     *
     * @return void|array
     */
    public function ____addAction()
    {
        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $form = new RoleForm('role', $data['section']);
            $form->setInputFilter(new RoleFilter);
            $form->setData($data);

            $status = 1;
            $roleData = array();
            if ($form->isValid()) {
                $values = $form->getData();
                foreach (array_keys($values) as $key) {
                    if (!in_array($key, $this->roleColumns)) {
                        unset($values[$key]);
                    }
                }
                $values['custom'] = 1;
                unset($values['id']);

                $row = $this->model()->createRow($values);
                $row->save();
                if ($row->id) {
                    Pi::registry('role')->flush();
                    $roleData = $row->toArray();
                    $message = __('Role data saved successfully.');
                } else {
                    $status = 0;
                    $message = __('Role data not saved.');
                }
            } else {
                $status = 0;
                $messages = $form->getMessages();
                $message = array();
                foreach ($messages as $key => $msg) {
                    $message[$key] = array_values($msg);
                }
            }
            return array(
                'status'    => $status,
                'message'   => $message,
                'data'      => $roleData,
            );
        } else {
            $type = $this->params('type', 'front');
            $form = new RoleForm('role', $type);
            $form->setAttribute(
                'action',
                $this->url('', array('action' => 'add'))
            );
            $this->view()->assign('title', __('Add a role'));
            $this->view()->assign('form', $form);
            $this->view()->setTemplate('system:component/form-popup');
        }
    }

    /**
     * Edit a role
     *
     * @return array|void
     */
    public function ____editAction()
    {
        if ($this->request->isPost()) {
            $data = $this->request->getPost();
            $form = new RoleForm('role', $data['section']);
            $form->setInputFilter(new RoleFilter);
            $form->setData($data);

            $status = 1;
            $roleData = array();
            if ($form->isValid()) {
                $values = $form->getData();
                $row = $this->model()->find($values['id']);
                $row->assign($values);
                try {
                    $row->save();
                    Pi::registry('role')->flush();
                    $roleData = $row->toArray();
                    $message = __('Role data saved successfully.');
                } catch (\Exception $e) {
                    $status = 0;
                    $message = __('Role data not saved.');
                }
            } else {
                $status = 0;
                $messages = $form->getMessages();
                $message = array();
                foreach ($messages as $key => $msg) {
                    $message[$key] = array_values($msg);
                }
            }
            return array(
                'status'    => $status,
                'message'   => $message,
                'data'      => $roleData,
            );
        } else {
            $id = $this->params('id');
            $row = $this->model()->find($id);
            $section = $row->section;
            $data = $row->toArray();
            $form = new RoleForm('role', $section);
            $form->setAttribute(
                'action',
                $this->url('', array('action' => 'edit'))
            );
            $form->setData($data);
            $this->view()->assign('title', __('Edit a role'));
            $this->view()->assign('form', $form);
            $this->view()->setTemplate('system:component/form-popup');
        }
    }

    /**
     * AJAX: Activate/deactivate a role
     *
     * @return array
     */
    public function ____activateAction()
    {
        $status = 1;
        $data = 0;
        $id = $this->params('id');
        $row = $this->model()->find($id);
        if (!$row['custom']) {
            $status = 0;
            $message =
                __('Only custom roles are allowed to activate/deactivate.');
        } else {
            if ($row->active) {
                $row->active = 0;
            } else {
                $row->active = 1;
            }
            $data = $row->active;
            $row->save();
            Pi::registry('role')->flush();
            $message = __('Role updated successfully.');
        }
        return array(
            'status'    => $status,
            'message'   => $message,
            'data'      => $data,
        );
    }

    /**
     * AJAX: Rename a role
     *
     * @return int
     */
    public function ____renameAction()
    {
        $id = $this->params('id');
        $title = $this->params('title');
        $row = $this->model()->find($id);
        $row->title = $title;
        $row->save();

        return 1;
    }

    /**
     * AJAX: Delete a role
     *
     * @return array
     */
    public function ____deleteAction()
    {
        $status = 1;
        $id = $this->params('id');
        $row = $this->model()->find($id);
        if (!$row['custom']) {
            $status = 0;
            $message = __('Only custom roles are allowed to delete.');
        } else {
            Pi::model('permission_rule')->delete(array('role' => $row->name));
            $row->delete();
            Pi::registry('role')->flush();
            $message = __('Role deleted successfully.');
        }

        $data = $this->getRoles($row->section);

        return array(
            'status'    => $status,
            'message'   => $message,
            'data'      => $data,
        );
    }

    /**
     * Check if a role name exists
     *
     * @return int
     */
    public function ____checkExistAction()
    {
        $role = _get('name');
        $row = Pi::model('role')->find($role, 'name');
        $status = $row ? 1 : 0;

        return array(
            'status' => $status
        );
    }

    /**
     * Users of a role
     */
    public function userAction()
    {
        $role   = $this->params('name', 'member');
        $op     = $this->params('op');
        $uid    = $this->params('uid');

        $model = Pi::model('user_role');
        $message = '';
        if ($op && $uid) {
            if (is_numeric($uid)) {
                $uid = (int) $uid;
            } else {
                $user = Pi::service('user')->getUser($uid, 'name');
                if ($user) {
                    $uid = $user->get('id');
                } else {
                    $uid = 0;
                }
            }
            if ($uid) {
                $data = array('role' => $role, 'uid' => $uid);
                $count = $model->count($data);
                if ('remove' == $op && $count) {
                    $model->delete($data);
                    $message = __('User removed.');
                    $data = array('uid' => $uid);
                } elseif ('add' == $op && !$count) {
                    $row = $model->createRow($data);
                    $row->save();
                    $message = __('User added.');
                    $data = array(
                        'uid'   => $uid,
                        'name'  => Pi::service('user')->get($uid, 'name')
                    );
                }

                return array(
                    'status'    => 1,
                    'message'   => $message,
                    'data'      => $data,
                );
            }
        }

        $page   = _get('page', 'int') ?: 1;
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $select = $model->select();
        $select->where(array('role' => $role))->limit(20)->offset($offset);
        $rowset = $model->selectWith($select);
        $uids = array();
        foreach ($rowset as $row) {
            $uids[] = (int) $row['uid'];
        }
        $users = Pi::service('user')->get($uids, array('uid', 'name'));
        $avatars = Pi::service('avatar')->getList($uids, 'small');
        array_walk($users, function (&$user, $uid) use ($avatars) {
            //$user['avatar'] = $avatars[$uid];
            $user['url'] = Pi::service('user')->getUrl('profile', $uid);
        });
        $count = count($uids);
        if ($count >= $limit) {
            $count = $model->count(array('role' => $role));
        }

        /*
        $paginator = Paginator::factory($count, array(
            'page'          => $page,
            'url_options'   => array(
                'params'    => array('role' => $role),
            ),
        ));
        */
        $roles = Pi::registry('role')->read();
        $title = sprintf(__('Users of role %s'), $roles[$role]['title']);
        if ($count > $limit) {
            $paginator = array(
                'page'    => $page,
                'count'   => $count,
                'limit'   => $limit
            );
        } else {
            $paginator = array();
        }

        $data = array(
            'title'     => $title,
            'users'     => array_values($users),
            'paginator' => $paginator,
        );

        return $data;
        /*
        $this->view()->assign(array(
            'title'     => $title,
            'role'      => $role,
            'count'     => $count,
            'users'     => $users,
            'message'   => $message,
            'paginator' => $paginator,
        ));

        $this->view()->setTemplate('role-user');
        */
    }

}

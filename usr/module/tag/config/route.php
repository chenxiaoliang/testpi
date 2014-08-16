<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt BSD 3-Clause License
 */

/**
 * Route specs
 *
 * @author Taiwen Jiang <taiwenjiang@tsinghua.org.cn>
 */
return array(
    // Tag route
    'tag'  => array(
        'name'      => 'tag',
        'type'      => 'Module\Tag\Route\Tag',
        'priority'  => 5,
        'options'   => array(
            'route'    => '/tag/term',
        ),
    ),
);

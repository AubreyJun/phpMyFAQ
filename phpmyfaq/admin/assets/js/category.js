/**
 * JavaScript functions for all FAQ category administration stuff
 *
 * This Source Code Form is subject to the terms of the Mozilla Public License,
 * v. 2.0. If a copy of the MPL was not distributed with this file, You can
 * obtain one at http://mozilla.org/MPL/2.0/.
 *
 * @package phpMyFAQ
 * @package   Administration
 * @author Thorsten Rinne <thorsten@phpmyfaq.de>
 * @copyright 2014-2019 phpMyFAQ Team
 * @license http://www.mozilla.org/MPL/2.0/ Mozilla Public License Version 2.0
 * @link https://www.phpmyfaq.de
 * @since 2014-06-02
 */

/*global $:false */

document.addEventListener('DOMContentLoaded', () => {
  'use strict';
  $(function () {
    $('.list-group-item').on('click', function () {
      $('.fas', this)
        .toggleClass('fa-caret-right')
        .toggleClass('fa-caret-down');
    });
  });
});

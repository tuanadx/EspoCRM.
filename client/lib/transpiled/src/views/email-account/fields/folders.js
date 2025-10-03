define("views/email-account/fields/folders", ["exports", "views/fields/array"], function (_exports, _array) {
  "use strict";

  Object.defineProperty(_exports, "__esModule", {
    value: true
  });
  _exports.default = void 0;
  _array = _interopRequireDefault(_array);
  function _interopRequireDefault(e) { return e && e.__esModule ? e : { default: e }; }
  /************************************************************************
   * This file is part of EspoCRM.
   *
   * EspoCRM – Open Source CRM application.
   * Copyright (C) 2014-2025 EspoCRM, Inc.
   * Website: https://www.espocrm.com
   *
   * This program is free software: you can redistribute it and/or modify
   * it under the terms of the GNU Affero General Public License as published by
   * the Free Software Foundation, either version 3 of the License, or
   * (at your option) any later version.
   *
   * This program is distributed in the hope that it will be useful,
   * but WITHOUT ANY WARRANTY; without even the implied warranty of
   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   * GNU Affero General Public License for more details.
   *
   * You should have received a copy of the GNU Affero General Public License
   * along with this program. If not, see <https://www.gnu.org/licenses/>.
   *
   * The interactive user interfaces in modified source and object code versions
   * of this program must display Appropriate Legal Notices, as required under
   * Section 5 of the GNU Affero General Public License version 3.
   *
   * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
   * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
   ************************************************************************/

  class _default extends _array.default {
    getFoldersUrl = 'EmailAccount/action/getFolders';
    setupOptions() {
      this.params.options = ['INBOX'];
    }
    fetchFolders() {
      return new Promise(resolve => {
        const data = {
          host: this.model.get('host'),
          port: this.model.get('port'),
          security: this.model.get('security'),
          username: this.model.get('username'),
          emailAddress: this.model.get('emailAddress'),
          userId: this.model.get('assignedUserId')
        };
        if (this.model.has('password')) {
          data.password = this.model.get('password');
        }
        if (!this.model.isNew()) {
          data.id = this.model.id;
        }
        Espo.Ajax.postRequest(this.getFoldersUrl, data).then(folders => {
          resolve(folders);
        }).catch(xhr => {
          Espo.Ui.error(this.translate('couldNotConnectToImap', 'messages', 'EmailAccount'));
          xhr.errorIsHandled = true;
          resolve(["INBOX"]);
        });
      });
    }
    actionAddItem() {
      Espo.Ui.notifyWait();
      this.fetchFolders().then(options => {
        Espo.Ui.notify(false);
        this.createView('addModal', this.addItemModalView, {
          options: options
        }).then(view => {
          view.render();
          view.once('add', item => {
            this.addValue(item);
            view.close();
          });
          view.once('add-mass', items => {
            items.forEach(item => {
              this.addValue(item);
            });
            view.close();
          });
        });
      });
    }
  }
  _exports.default = _default;
});
//# sourceMappingURL=folders.js.map ;
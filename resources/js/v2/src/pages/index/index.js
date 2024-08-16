/*
 * index.js
 * Copyright (c) 2024 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

import '../../boot/bootstrap.js';


let index = function () {
    return {
        pageProperties: {
            connectionError: false,
            connectionErrorMessage: '',
        },
        loadingFunctions: {
            file: true,
            gocardless: true,
            spectre: true,
            simplefin: true
        },
        errors: {
            spectre: '',
            gocardless: '',
            simplefin: '',
        },
        importFunctions: {
            file: false,
            gocardless: false,
            spectre: false,
            simplefin: false
        },
        functionName() {

        },
        init() {
            this.checkFireflyIIIConnection();
        },
        checkFireflyIIIConnection() {
            let validateUrl = './token/validate';
            let tokenPageUrl = './token';
            window.axios.get(validateUrl).then((response) => {
                let message = response.data.result;
                console.log('message is ', message)

                if ('OK' === message) {
                    this.loadingFunctions.file = false;
                    this.importFunctions.file = true;
                    return;
                }
                // disable all
                this.loadingFunctions.file = false;
                this.loadingFunctions.gocardless = false;
                this.loadingFunctions.spectre = false;
                this.loadingFunctions.simplefin = false;

                this.importFunctions.file = false;
                this.importFunctions.gocardless = false;
                this.importFunctions.spectre = false;
                this.importFunctions.simplefin = false;

                this.pageProperties.connectionError = true;
                this.pageProperties.connectionErrorMessage = response.data.message;
            }).catch((error) => {
                this.loadingFunctions.file = false;
                this.loadingFunctions.gocardless = false;
                this.loadingFunctions.spectre = false;
                this.loadingFunctions.simplefin = false;

                this.importFunctions.file = false;
                this.importFunctions.gocardless = false;
                this.importFunctions.spectre = false;
                this.importFunctions.simplefin = false;

                this.pageProperties.connectionError = true;
                this.pageProperties.connectionErrorMessage = error;
            }).finally(() => {
                if(false === this.pageProperties.connectionError) {
                    this.checkSpectreConnection();
                    this.checkGoCardlessConnection();
                    this.checkSimpleFinConnection();
                }
            });

        },
        checkSpectreConnection() {
            let validateUrl = './validate/spectre';
            window.axios.get(validateUrl).then((response) => {
                let message = response.data.result;
                if ('NODATA' === message ||  'OK' === message) {
                    this.loadingFunctions.spectre = false;
                    this.importFunctions.spectre = true;
                    return;
                }
                this.loadingFunctions.spectre = false;
                this.importFunctions.spectre = false;
                this.errors.spectre = 'The Spectre / Salt Edge API is configured incorrectly and cannot be used to import data.';
            }).catch((error) => {
                this.loadingFunctions.spectre = false;
                this.importFunctions.spectre = false;
                this.errors.spectre = 'The Spectre / Salt Edge API is configured incorrectly and cannot be used to import data.';
            });
        },
        checkSimpleFinConnection() {
            let validateUrl = './validate/simplefin';
            window.axios.get(validateUrl).then((response) => {
                let message = response.data.result;
                if ('NODATA' === message ||  'OK' === message) {
                    this.loadingFunctions.simplefin = false;
                    this.importFunctions.simplefin = true;
                    return;
                }
                this.loadingFunctions.simplefin = false;
                this.importFunctions.simplefin = false;
                this.errors.simplefin = 'Please set a valid APP_KEY to use the SimpleFIN importer.';
            }).catch((error) => {
                this.loadingFunctions.simplefin = false;
                this.importFunctions.simplefin = false;
                this.errors.simplefin = 'Please set a valid APP_KEY to use the SimpleFIN importer.';
            });
        },
        checkGoCardlessConnection() {
            let validateUrl = './validate/nordigen';
            window.axios.get(validateUrl).then((response) => {
                let message = response.data.result;
                if ('NODATA' === message ||  'OK' === message) {
                    this.loadingFunctions.gocardless = false;
                    this.importFunctions.gocardless = true;
                    return;
                }
                this.loadingFunctions.gocardless = false;
                this.importFunctions.gocardless = false;
                this.errors.gocardless = 'The GoCardless API is configured incorrectly and cannot be used to import data.';
            }).catch((error) => {
                this.loadingFunctions.gocardless = false;
                this.importFunctions.gocardless = false;
                this.errors.gocardless = 'The GoCardless API is configured incorrectly and cannot be used to import data.';
            });
        }
    }
}


function loadPage() {
    Alpine.data('index', () => index());
    Alpine.start();
}

// wait for load until bootstrapped event is received.
document.addEventListener('data-importer-bootstrapped', () => {
    console.log('Loaded through event listener.');
    loadPage();
});
// or is bootstrapped before event is triggered.
if (window.bootstrapped) {
    console.log('Loaded through window variable.');
    loadPage();
}

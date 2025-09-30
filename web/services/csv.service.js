angular.module('app')
    .factory('csvservice', function ($q, $http) {
        const API_BASE_URL = 'http://localhost/php/';

        return {
            upload: function (params) {
                return $http({
                    method: 'POST',
                    url: API_BASE_URL + 'csv.php',
                    data: params,
                    headers: { 'Content-Type': 'application/json' }
                }).then(res => res.data);
            }
        };
    });

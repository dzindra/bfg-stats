/*

 Copyright 2013 Jindrich Dolezy (dzindra)

 Licensed under the Apache License, Version 2.0 (the "License");
 you may not use this file except in compliance with the License.
 You may obtain a copy of the License at

 http://www.apache.org/licenses/LICENSE-2.0

 Unless required by applicable law or agreed to in writing, software
 distributed under the License is distributed on an "AS IS" BASIS,
 WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 See the License for the specific language governing permissions and
 limitations under the License.

 */
'use strict';

var app = angular.module('app', ['ngCookies']);

app.config(['$routeProvider', function ($routeProvider) {
    $routeProvider.
        when('/stats', {templateUrl: 'partials/stats.html', controller: 'StatsCtrl'}).
        when('/pools', {templateUrl: 'partials/pools.html', controller: 'PoolsCtrl'}).
        otherwise({redirectTo: '/stats'});
}]);

app.service('RefreshService', ['$timeout', '$q', '$rootScope', function ($timeout, $q, $rootScope) {
    var refreshService = {
        timer: null,
        lastRefresh: 0,
        interval: 0,
        deferred: null,
        func: null,
        paused: false
    };

    var runTimer = function () {
        if (!refreshService.timer && refreshService.interval > 0 && !refreshService.paused) {
            refreshService.timer = $timeout(function () {
                refreshService.timer = null;
                refresh();
            }, refreshService.interval * 1000);
        }
    };

    var stopTimer = function () {
        if (refreshService.timer) {
            $timeout.cancel(refreshService.timer);
            refreshService.timer = null;
        }
    };

    var refreshInterval = function (delay) {
        if (typeof delay !== 'undefined') {
            stopTimer();
            refreshService.interval = delay;
            if (!refreshService.deferred)
                runTimer();
        }

        return refreshService.interval;
    };

    var refreshFunc = function (func) {
        if (typeof func !== 'undefined') {
            stopTimer();
            refreshService.func = func;
            refresh();
        }
        return refreshService.func;
    };

    var refresh = function () {
        if (refreshService.deferred)
            return;

        stopTimer();

        if (typeof refreshService.func === 'function') {
            $rootScope.$broadcast('refreshStarted');
            refreshService.deferred = $q.defer();
            refreshService.deferred.promise.then(function () {
                refreshService.lastRefresh = new Date();
                refreshService.error = null;

                $rootScope.$broadcast('refreshFinished', true, null);
                refreshService.deferred = null;
                runTimer();
            }, function (error) {
                if (error)
                    refreshService.error = error;

                $rootScope.$broadcast('refreshFinished', false, error);
                refreshService.deferred = null;
                runTimer();
            });
            refreshService.func(refreshService.deferred);
        }
    };

    return {
        refresh: refresh,
        refreshFunc: refreshFunc,
        interval: refreshInterval,
        error: function () {
            return refreshService.error
        },
        refreshing: function () {
            return refreshService.deferred != null
        },
        lastRefresh: function () {
            return refreshService.lastRefresh
        },
        refreshPaused: function (value) {
            if (typeof value !== 'undefined') {
                refreshService.paused = value;
                if (value) stopTimer(); else runTimer();
            }
            return refreshService.paused;
        }

    };
}]);

app.constant('cookieName', 'minerStatCookie');
app.run(['$cookieStore', 'RefreshService', 'cookieName', function ($cookieStore, RefreshService, cookieName) {
    RefreshService.interval($cookieStore.get(cookieName) || 5);

    var orig = RefreshService.interval;
    RefreshService.interval = function (delay) {
        if (typeof delay !== 'undefined')
            $cookieStore.put(cookieName, delay);

        return orig(delay);
    };
}]);


app.controller('MainCtrl', ['$scope', function ($scope) {
    $scope.$on('refreshStarted', function () {
        $scope.working = true;
    });
    $scope.$on('refreshFinished', function () {
        $scope.working = false;
    });
}]);

app.controller('MenuCtrl', ['$scope', '$location', function ($scope, $location) {
    $scope.isMenuActive = function (value) {
        return $location.path() == value ? "active" : "";
    };
}]);

app.controller('StatsCtrl', ['$scope', '$http', 'RefreshService', function ($scope, $http, RefreshService) {

    RefreshService.refreshFunc(function (deferred) {
        var g = $http.post('php/stats.php', {}, {cache: false});
        g.success(function (data) {
            if (data.status == 1) {
                $scope.devices = data.devices;
                $scope.pools = data.pools;

                deferred.resolve();
            } else {
                if (!$scope.devices)
                    $scope.devices = [];
                if (!$scope.pools)
                    $scope.pools = [];

                deferred.reject(data.error);
            }
        });
        g.error(function (data, status) {
            deferred.reject("HTTP error " + status);
        });
    });


    $scope.isRefreshActive = function (value) {
        return value == RefreshService.interval() ? 'active' : '';
    };

    $scope.doRefresh = RefreshService.refresh;
    $scope.setRefresh = RefreshService.interval;

    $scope.$on('refreshFinished', function (event, status, error) {
        $scope.error = error;
        $scope.lastRefresh = RefreshService.lastRefresh();
    });

    $scope.$on('$destroy', function () {
        RefreshService.refreshFunc(null);
    });

}]);

app.controller('PoolsCtrl', ['$scope', '$http', '$rootScope', function ($scope, $http, $rootScope) {
    $scope.pool = {};
    $scope.pools = [];
    $scope.buttonsDisabled = false;

    var call = function (params, callback) {
        if ($scope.buttonsDisabled)
            return;

        $scope.buttonsDisabled = true;
        $scope.message = '';
        $scope.error = '';
        $rootScope.$broadcast("refreshStarted");

        var r = $http.post('php/pools.php', params, {cache: false});
        r.success(function (data) {
            $scope.buttonsDisabled = false;
            if (data.status == 1) {
                if (callback) callback(data);
                $scope.message = data.message;
                $scope.pools = data.pools;
                $rootScope.$broadcast('refreshFinished', true, null);
            } else {
                $scope.error = data.error;
                $rootScope.$broadcast('refreshFinished', false, data.error);
            }
        });
        r.error(function (data) {
            $scope.buttonsDisabled = false;
            $scope.error = data.error;
            $rootScope.$broadcast('refreshFinished', false, data.error);
        });
    };

    $scope.add = function () {
        var pool = angular.copy($scope.pool);
        pool.command = "add";

        call(pool, function () {
            $scope.pool = {};
        });
    };

    $scope.deletePool = function (id) {
        call({command: "remove", id: id});
    };

    $scope.topPool = function (id) {
        call({command: "top", id: id});
    };

    $scope.enablePool = function (id) {
        call({command: "enable", id: id});
    };

    $scope.disablePool = function (id) {
        call({command: "disable", id: id});
    };

    $scope.refreshPools = function () {
        call({command: "list"});
    };

    $scope.isPoolDisabled = function (pool) {
        return pool.status == 'Disabled';
    };

    $scope.refreshPools();

}]);

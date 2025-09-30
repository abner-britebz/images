angular.module('app')
    .controller('csvcontroller', function ($scope, csvservice, $timeout) {

        $scope.form = {
            imageColumn: '',
            itemColumn: '',
            excelFile: null,
            rowStart: 2
        };

        $scope.uploading = false;
        $scope.message = '';

        $scope.uploadCSV = function () {
            if (!$scope.form.excelFile || !$scope.form.itemColumn) {
                alert("Select file and item column");
                return;
            }

            $scope.uploading = true;
            $scope.message = "Uploading...";

            var file = $scope.form.excelFile;
            var reader = new FileReader();

            reader.onload = function (e) {
                $scope.$apply(function () {
                    // Prepare JSON payload
                    var payload = {
                        fileName: file.name,
                        fileType: file.type,
                        fileData: e.target.result.split(',')[1], // Base64 only
                        imageColumn: $scope.form.imageColumn || '',
                        itemColumn: $scope.form.itemColumn,
                        rowStart: $scope.form.rowStart || 2
                    };

                    // Call service
                    csvservice.upload(payload)
                        .then(function (res) {
                            $scope.message = "Upload successful!";
                            console.log("Upload success:", res);
                        })
                        .catch(function (err) {
                            $scope.message = "Upload failed!";
                            console.error("Upload error:", err);
                        })
                        .finally(function () {
                            $timeout(function () {
                                $scope.uploading = false;
                            }, 500);
                        });
                });
            };

            reader.onerror = function (err) {
                $scope.$apply(function () {
                    $scope.uploading = false;
                    $scope.message = "Error reading file!";
                    console.error(err);
                });
            };

            // Convert file to Base64
            reader.readAsDataURL(file);
        };
    });

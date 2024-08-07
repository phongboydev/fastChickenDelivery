<?php

namespace App\Support;

use App\Exceptions\HumanErrorException;
use App\Models\Approve;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use GuzzleHttp\Client;
use App\Models\ClientLogDebug;

class HanetHelper
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }
    /**
     *  Call API Hanet
     */
    public function callApi($url, $data, $method = 'POST', $endpoint = '')
    {
        if($endpoint != '') {
            $endpoint = $endpoint . '/' . $url;
        } else {
            $endpoint = config('hanet.partner_url') . '/' . $url;
        }

        if ($method == 'GET') {
            $response = $this->client->request($method, $endpoint);
        }

        if ($method == 'POST') {
            $response = $this->client->request($method, $endpoint, $data);
        }
        
        return $response;
    }
    /**
     * get Checkin By PlaceId In Timestamp
     * Cho phép liệt kê data checkin trong khoảng thời gian cùng một tháng những người đã đăng kí nhận diện với HANET
     * Lấy danh sách checkin theo dạng phân trang. mặc định sẽ trả về 500 item nếu không truyền vào page size.
     * @param $data is array
     * token: <ACCESS_TOKEN>
     * placeID là ID của địa điểm muốn lấy data checkin
     * from : thời gian bắt đầu muốn lấy data checkin
     * to : thời gian kết thúc muốn lấy data checkin
     * Điều kiện :
     *  from < to và thời gian from và to phải trong cùng 1 tháng.
     * devices: là danh sách device muốn filter để lấy data checkin.
     * Nếu không truyền param devices thì sẽ mặc định lấy hết data checkin của tất cả device trong place đó.
     * exDevices: filter ngoại trừ danh sách device, danh sách id device cách nhau bởi dấu phẩy.
     * exType: filter ngoại trừ danh sách exType.Danh sách id exType cách nhau bởi dấu phẩy trong đó 0: nhân viên, 1: khách hàng, 2: người lạ
     * aliasID: filter theo aliasID
     * personID: filter theo personID
     * personIDs: filter theo danh sách id person, id person cách nhau bởi dấu phẩy.
     * aliasIDs: filter theo danh sách id alias, id alias cách nhau bởi dấu phẩy.
     * page: số page cần lấy data
     * size: số lượng item cần lấy của 1 page.(tối đa 500 item)
     * @return json.
     */
    public function getCheckinByPlaceIdInTimestamp($data)
    {
        $respsonse = $this->callApi('person/getCheckinByPlaceIdInTimestamp', ['form_params' => $data]);
        $result  = $this->checkStatusResponse($respsonse, 'getCheckinByPlaceIdInTimestamp', $data);        
        return  $result;
    }

    /**
     * Cho phép tạo địa điểm mới vào tài khoản User.
     * @param $data is array
     * token: <ACCESS_TOKEN>
     * @return json.
     */
    public function addPlace($data)
    {
        $respsonse = $this->callApi('place/addPlace', ['form_params' => $data]);
        $result  = $this->checkStatusResponse($respsonse, 'addPlace', $data);        
        return  $result;
    }

    /**
     * HANET quản lý thiết bị theo địa điểm.
     * 1 tài khoản HANET có thể quản lý nhiều địa điểm
     * 1 địa điểm có thể có nhiều thiết bị trong địa điểm đó
     * API này cho phép lấy danh sách địa điểm của user. Lưu ý là API này chỉ cho phép lấy những địa điểm của user tạo ra, 
     * địa điểm do người khác chia sẻ cho user sẽ không được trả về
     * @param $data is array
     * token: <ACCESS_TOKEN>
     * name: Tên địa điểm
     * address: Địa chỉ của địa điểm
     * @return json.
     */
    public function getPlaces($data)
    {
        $respsonse =  $this->callApi('place/getPlaces', ['form_params' => $data]);
        $result  = $this->checkStatusResponse($respsonse, 'getPlaces', $data);        
        return  $result;
    }

    /**
     * Khi có event xảy ra trên thiết bị của HANET, backend của HANET sẽ chủ động đẩy data về cho client thông qua webhook mà client cung cấp. 
     * Nếu client có nhiều tập user khác nhau, mà muốn phân biệt data thuộc về user nào, thì cần update cho HANET biết ID/Token của user đó. Gọi là partner_token
     * @param $data is array
     * access_token: <ACCESS_TOKEN>
     * partner_token: token của partner, tương ứng với user hiện tại ( NOTE: Bên Hanet trả lời nhập gì cũng được, không có ràng buộc )
     * @return json.
     */
    public function updateToken($data)
    {
        $respsonse =  $this->callApi('partner/updateToken', ['form_params' => $data]);
        $result  = $this->checkStatusResponse($respsonse, 'updateToken', $data);        
        return  $result;
    }

    /**
     * Đối tác có thể xoá liên kết app, bằng cách sử dụng API này để update cho HANET.
     * @param $data is array
     * access_token: <ACCESS_TOKEN>
     * @return json.
     */
    public function removeUserPartner($data)
    {
        $respsonse =  $this->callApi('partner/removeUserPartner', ['form_params' => $data]);
        $result  = $this->checkStatusResponse($respsonse, 'removeUserPartner', $data);        
        return  $result;
    }
    /**
     * Lấy danh sách person của tất cả địa điểm thông qua aliasID. Thông tin response bao gồm các thông tin cơ bản là: personID, name, avatar.
     * @param $data is array
     * access_token: <ACCESS_TOKEN>
     * aliasID: <aliasID>
     * @return json.
     */
    public function getUserInfoByAliasID($data) 
    {
        $respsonse =  $this->callApi('person/getUserInfoByAliasID', ['form_params' => $data]);
        $result  = $this->checkStatusResponse($respsonse, 'getUserInfoByAliasID', $data);        
        return  $result;
    }
    /**
     * Lấy danh sách person của tất cả địa điểm thông qua aliasID. Thông tin response bao gồm các thông tin cơ bản là: personID, name, avatar.
     * token <ACCESS_TOKEN>
     * aliasID <aliasID>
     * placeIDs <placeID1><placeID2><placeID3>,...
     * 
     */

    public function getListByAliasIDAllPlace($data)
    {
        $respsonse =  $this->callApi('person/getListByAliasIDAllPlace', ['form_params' => $data]);
        $result  = $this->checkStatusResponse($respsonse, 'getListByAliasIDAllPlace', $data);        
        return  $result;
    }
    /**
     * Lấy danh sách các thiết bị của user. Thông tin response bao gồm các thông tin cơ bản là: deviceID, deviceName, placeName, address.
     * HANET quản lý thiết bị theo địa điểm.
     * 1 tài khoản HANET có thể quản lý nhiều địa điểm
     * 1 địa điểm có thể có nhiều thiết bị trong địa điểm đó
     * @param $data is array
     *
     * @return json.
     *
     */
    public function getListDevice($data)
    {
        $respsonse = $this->callApi('device/getListDevice', ['form_params' => $data]);
        $result  = $this->checkStatusResponse($respsonse, 'getListDevice', $data);        
        return  $result;
    }
    /**
     * Lấy danh sách person của một địa điểm.
     * Thông tin response bao gồm các thông tin cơ bản là: personID, name, avatar.
     * api có hỗ trợ phân trang theo page, size.
     * mặc định sẽ trả về 50 item, nếu muốn lấy nhiều hơn nữa thì truyền thêm param page và size.
     * @param $data is array
     * access_token: <ACCESS_TOKEN>
     * placeID: <placeID> - ID của địa điểm (required)
     * type: <0 or 1> - Nhân viên: 0, Khác hàng:1, Tất cả : -1
     * page: <page> - số page
     * size: <size> - số lượng item cần lấy trong 1 page
     * @return json.
     */
    public function getListByPlace($data)
    {
        // $headers = ['Content-Type' => 'application/json'];
        $respsonse =  $this->callApi('person/getListByPlace', ['form_params' => $data]);
        $result  = $this->checkStatusResponse($respsonse, 'getListByPlace', $data);        
        return  $result;
    }
    /**
     * Đăng kí hình ảnh khuôn mặt với tài khoản user.
     * Để thiết bị nhận dạng được người nào đó thì cần phải đăng kí hình ảnh của người đó đó với hệ thống
     * HANET quản lý dữ liệu khuôn mặt theo địa điểm chứ không phải theo thiết bị, 
     * nên các thiết bị nếu được thêm vào cùng 1 địa điểm thì sẽ được đồng bộ dữ liệu khuôn mặt với nhau.
     * Chỉ cần dùng 1 hình là đủ để đăng kí cho 1 người
     * Vài yêu cầu khi dùng API này:
     * API Post dùng url
     * Hình trước khi submit phải được resize về đúng kích thước 1280*736.
     * Ít nhất 1 thiết bị trong trong địa điểm có online thì mới đăng kí user được.
     * Nếu có nhiều thiết bị trong trong cùng 1 địa điểm, thì sau khi đăng kí thành công, tất cả thiết bị đều được đăng kí user đó
     * Cần thiết lập timeout cho API này từ 10 - 30s
     * Đối tác cần gửi ID của người muốn đăng kí qua cho HANET thông qua param aliasID. HANET sẽ dùng ID này để sử dụng cho các API khác như update, delete người đăng kí
     * Nếu đối tác không cần đặt ID riêng cho user thì không cần truyền param aliasID, và có thể dùng personID của hanet để sử dụng các api update, delete cho user
     * @param $data is array
     * Content-Type : application/x-www-form-urlencoded
     * token : <ACCESS_TOKEN>
     * name : tên của người muốn đăng kí
     * url : <url> Phải resize về đúng kích thước 1280*736(w * h), chỉ hỗ trợ JPG, PNG, JPEG
     * aliasID : <aliasID> ID của người cần đăng kí vào hệ thống.
     * placeID : <placeID> ID của địa điểm cần thêm nhân viên
     * title : Chức danh nhân viên
     * type : 0 Nhân viên: 0, Khách hàng: 1. Mặc định sẽ là 0
     * @return json.
     * 
     */
    public function registerByUrl($data)
    {
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $respsonse =  $this->callApi('person/registerByUrl', ['form_params' => $data, 'headers' => $headers]);
        $result  = $this->checkStatusResponse($respsonse, 'registerByUrl', $data);        
        return  $result;
    }
    
    /**
     * Trong trường hợp đã đăng kí hình ảnh của 1 người, và người đó đã hoạt động 1 thời gian trong hệ thống, nếu có nhu cầu thay đổi hình ảnh nhận diện, 
     * thì có thể sử dụng API này, thì thay vì phải xoá hình người cũ sau đó đăng kí lại, việc này sẽ dẫn đến vấn đề là mất hết dữ liệu của người đó
     * Vài yêu cầu khi dùng API này:
     * API Post dùng url
     * Hình trước khi submit phải được resize về đúng kích thước 1280*738.
     * Ít nhất 1 thiết bị trong trong địa điểm có online thì mới đăng kí user được.
     * Nếu có nhiều thiết bị trong trong cùng 1 địa điểm, thì sau khi đăng kí thành công, tất cả thiết bị đều được đăng kí user đó
     * Cần thiết lập timeout cho API này từ 10 - 30s
     * @param $data is array
     * #HEADERS
     * Content-Type : application/x-www-form-urlencoded
     * #Body
     * token : <ACCESS_TOKEN>
     * url : <url> url là link ảnh của person cần thay đổi. Phải resize về đúng kích thước 1280*736, chỉ hỗ trợ JPG, PNG, JPEG
     * personID : <personID> ID của người cần update hình
     * placeID : <placeID> ID của địa điểm
     * 
     * @return json.
     */
    public function updateByFaceUrlByPersonID($data) 
    {        
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $respsonse =  $this->callApi('person/updateByFaceUrlByPersonID', ['form_params' => $data, 'headers' => $headers]);
        $result  = $this->checkStatusResponse($respsonse, 'updateByFaceUrlByPersonID', $data);        
        return  $result;
    }
    /**
     * Cho phép thay đổi các thông tin text của người đã đăng kí hình ảnh với HANET
     * Đối tác Có thể dùng aliasID hoặc personID để update thông tin cho person.
     * Content-Type : application/json
     * token : <ACCESS_TOKEN>
     * updates : {"name": "Như Kim","title":"develop"} - thông tin chỉnh sữa bao gồm name và title
     * aliasID : <aliasID> -  ID của người cần update thông tin. Có thể dùng aliasID hoặc personID
     * placeID : <placeID> - ID của địa điểm
     * personID : <personID>
     * 
     */
    public function personUpdate($data) 
    {
        logger(['$data' => $data]);
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $respsonse =  $this->callApi('person/update', ['form_params' => $data, 'headers' => $headers]);
        // logger(['$response API ' => $respsonse ]);
        $result  = $this->checkStatusResponse($respsonse, 'personUpdate', $data);        
        return  $result;
    }

    /**
     * Cho phép thay đổi các thông tin text của người đã đăng kí hình ảnh với HANET.
     * Đối tác Có thể dùng aliasID hoặc personID để update thông tin cho person.
     * @param $data is array
     * #HEADERS
     * Content-Type : application/json
     * #Body
     * token : <ACCESS_TOKEN>     
     * aliasID : <aliasID> - ID của người cần update thông tin. ID Có thể dùng aliasID hoặc personID
     * placeID : <placeID> - ID của địa điểm
     * name : name
     * title : title 
     * @return json.
     */
    public function personUpdateInfo($data)
    {
        $headers = ['Content-Type' => 'text/plain'];
        $respsonse =  $this->callApi('person/updateInfo', ['form_params' => $data, 'headers' => $headers]);
        $result  = $this->checkStatusResponse($respsonse, 'personUpdateInfo', $data);        
        return  $result;
    }

    /**
     * API cho phép lấy danh sách phòng ban của một place ID.
     * có thể filter theo name của phòng ban.
     * Get list phòng ban có phân trang theo page và size.
     * @param $data is array
     * token : {{access_token}} - token của đối tác
     * placeID : <placeID> - id cua place
     * keyword : <keyword> - filter theo name của deparment
     * page : <page> - số trang
     * size : <size> - số item của mỗi trang
     * 
     * @return json.
     */
    public function departmentList($data)
    {
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $respsonse =  $this->callApi('department/list', ['form_params' => $data, 'headers' => $headers]);
        $result  = $this->checkStatusResponse($respsonse, 'departmentList', $data);        
        return  $result;
    }

    /**
     * API cho phép lấy danh sách person của một phòng ban.
     * Có phân trang theo page size. mỗi page lấy tối đa 50 item
     * @param $data is array
     * token : {{access_token}} - token của đối tác
     * departmentID : <departmentID> - id của phòng ban
     * keyword : <keyword> - name của phòng ban cần filter
     * page : <page> - số trang
     * size : <size> - số item của 1 trang
     * 
     * @return json.
     */
    public function departmentListPerson($data)
    {
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $respsonse =  $this->callApi('department/list-person', ['form_params' => $data, 'headers' => $headers]);
        $result  = $this->checkStatusResponse($respsonse, 'departmentListPerson', $data);        
        return  $result;
    }

    /**
     * API tạo một phòng ban theo placeID, bao gồm placeID, tên và mô tả về phòng ban
     * @param $data is array
     * token : {{access_token}} - token của đối tác
     * placeID : <placeID> - id của place required
     * name : <name> - tên của phòng ban required
     * desc : <description> - mô tả về phòng ban
     * 
     * @return json.
     */
    public function departmentCreate($data)
    {
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $respsonse =  $this->callApi('department/create', ['form_params' => $data, 'headers' => $headers]);
        $result  = $this->checkStatusResponse($respsonse, 'departmentCreate', $data);        
        return  $result;
    }

    /**
     * API cho phép chỉnh sửa tên và mô tả của một phòng ban.
     * @param $data is array
     * token : {{access_token}} - token của đối tác
     * id : <id> - id của phòng ban required
     * name : <name> - name của phòng ban required
     * desc : <desc> - mô tả về phòng ban
     * @return json.
     */
    public function departmentUpdate($data)
    {
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $respsonse =  $this->callApi('department/update', ['form_params' => $data, 'headers' => $headers]);
        $result  = $this->checkStatusResponse($respsonse, 'departmentUpdate', $data);        
        return  $result;
    }

    /**
     * API cho phép đối tác xoá một phòng ban.
     * 
     * token : {{access_token}} - token của đối tác
     * id : <id> - id của phòng ban required
     * 
     */
    public function departmentRemove($data)
    {
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $respsonse =  $this->callApi('department/remove', ['form_params' => $data, 'headers' => $headers]);
        $result  = $this->checkStatusResponse($respsonse, 'departmentRemove', $data);        
        return  $result;
    }

    /**
     * API cho phép đối tác thêm một hoặc nhiều person vào phòng ban.
     * Mỗi id person cách nhau bằng dấu phẩy
     * 
     * @param $data is array
     * token : {{access_token}} - token của đối tác
     * departmentID : <departmentID> - id của phòng ban
     * personIDs : <personID1>,<personID2>,<personID3>,.... - list id của person cách nhau bằng dấy phẩy
     * @return json.
     */

    public function departmentAddPerson($data)
    {
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $respsonse =  $this->callApi('department/remove-person', ['form_params' => $data, 'headers' => $headers]);
        $result  = $this->checkStatusResponse($respsonse, 'departmentAddPerson', $data);        
        return  $result;
    }

    /**
     * API cho phép xoá một person ra khỏi phòng ban
     * 
     * @param $data is array
     * token : {{access_token}} - token của đối tác
     * departmentID : <departmentID> - id của phòng ban
     * personID : personID - id của person cần xoá
     * @return json.
     */
    public function departmentRemovePerson($data)
    {
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $respsonse =  $this->callApi('department/add-person', ['form_params' => $data, 'headers' => $headers]);
        $result  = $this->checkStatusResponse($respsonse, 'departmentRemovePerson', $data);        
        return  $result;
    }

    /**
     * API này Cho phép đối tác lấy token
     * Chú ý: param grant_type
     * Case1: Dùng grant_type là authorization_code để lấy token.
     * Case2: Dùng grant_type là refresh_token để lấy lại refeshToken khi token hết hạn
     */
    public function refreshToken($data) {
        
        $headers = ['Content-Type' => 'application/x-www-form-urlencoded'];
        $respsonse =  $this->callApi('token', ['form_params' => $data, 'headers' => $headers], 'POST',config('hanet.oauth_url'));
        $result = $this->checkStatusResponse($respsonse, 'refreshToken', $data);
        return $result;
    }
    
    // check status call API Hanet

    private function checkStatusResponse( $response, $namenFunction, $dataRequest ) {
        if($response->getStatusCode()!= '200') {

            $logDebug = new ClientLogDebug();
            $logDebug->type = 'Hanet ' . $namenFunction;
            $logDebug->data_log = json_encode($dataRequest);
            $logDebug->note = json_encode($response->getHandlerErrorData());
            $logDebug->save();
            return false;
        }
        return $response->getBody();
    }

    public static function checkSkipHanet($client_id, $skip_hanet, $source, $target_id, $target_type = Timesheet::class)
    {
        $status = false;
        if ($source === 'Hanet') {
            if ($skip_hanet) {
                $status = true;
            } else {
                $hasEdit = Approve::where('client_id', $client_id)
                    ->where([
                        'type' => 'CLIENT_REQUEST_TIMESHEET_EDIT_WORK_HOUR',
                        'target_type' => $target_type,
                        'target_id' => $target_id,
                        'is_final_step' => 1
                    ])
                    ->whereNotNull('approved_at')
                    ->exists();

                $status = $hasEdit;
            }
        }

        return $status;
    }
}

# Fixes for SupplierSelectionReportIndex.vue

## Issues to Address:
1. "onMounted is called when there is no active component instance" - Ensure lifecycle hooks are used correctly
2. "TypeError: val.every is not a function" - Ensure reports data is properly handled as array

## Steps:
1. Add proper validation for reports prop to ensure it's always an array
2. Add defensive programming to handle cases where reports might be null/undefined
3. Ensure proper initialization of selectedReports
4. Add error handling for DataTable operations

## Files to Modify:
- resources/js/Pages/SupplierSelectionReportIndex.vue

## Progress:
- [x] Implement fixes for array handling
- [x] Add proper validation for reports data
- [x] Test the changes

---

# Xử lý User có cả Role "Trưởng phòng" và "Giám đốc" (25/11/2025)

## Vấn đề:
- Thực tế công ty có 1 User vừa là Trưởng phòng vừa là Giám đốc
- Hệ thống chỉ cho phép 1 User có 1 Role
- Khi Nhân viên mua hàng tạo phiếu xong, cần tự động đẩy thẳng tới Nhân viên Kiểm Soát nếu người duyệt là Giám đốc

## Giải pháp đã implement:
### Phương án 3: Kiểm tra theo Department code

**Logic:**
- User thuộc `department->code = 'GD'` (Ban Giám Đốc) → coi như là Giám đốc
- User có role = "Giám đốc" → coi như là Giám đốc
- Khi gửi duyệt cho Giám đốc → tự động approve bước Manager & chuyển thẳng tới Kiểm Soát

**Các thay đổi:**

1. **SupplierSelectionReportController.php - index()**
   - Lấy danh sách cả "Trưởng phòng" và "Giám đốc" để chọn
   - Hiển thị role trong tên (display_name) để người dùng biết

2. **SupplierSelectionReportController.php - requestManagerToApprove()**
   - Kiểm tra nếu manager là Giám đốc hoặc thuộc Ban Giám Đốc
   - Tự động approve bước Manager với note "Tự động phê duyệt (Giám đốc)"
   - Gửi email thẳng tới Nhân viên Kiểm Soát
   - Skip bước pending_manager_approval

3. **SupplierSelectionReportIndex.vue**
   - Thay đổi label dialog thành "Gửi phê duyệt"
   - Hiển thị `display_name` (bao gồm role) trong dropdown
   - Thêm note giải thích nếu chọn Giám đốc thì tự động approve

## Ưu điểm:
✅ Không cần sửa database structure
✅ Không cần migration
✅ Không ảnh hưởng data cũ
✅ Logic nghiệp vụ hợp lý (người quyền cao hơn tự động được approve bước thấp hơn)
✅ Dễ maintain và test

## Files đã sửa:
- app/Http/Controllers/SupplierSelectionReportController.php
- resources/js/Pages/SupplierSelectionReportIndex.vue

---

# Feature: Báo cáo khẩn cấp - Bỏ qua Kiểm Soát Nội Bộ (25/11/2025)

## Vấn đề:
Có trường hợp báo cáo cần duyệt gấp, cần bỏ qua bước Kiểm Soát Nội Bộ (Auditor) để tăng tốc độ duyệt.

## Giải pháp đã implement:
### Thêm trường `is_urgent` (Boolean)

**Logic:**
- Manager (Trưởng phòng) đánh dấu "Báo cáo khẩn cấp" khi approve
- Nếu `is_urgent = true` → Tự động skip bước Auditor, status chuyển thẳng sang `pending_director_approval`
- Auditor fields được tự động điền: `auditor_audited_result = 'approved'`, `auditor_audited_notes = 'Tự động bỏ qua (Báo cáo khẩn cấp)'`

**Các thay đổi:**

1. **Migration: `2025_11_25_132831_add_is_urgent_to_supplier_selection_reports_table.php`**
   - Thêm column `is_urgent` (boolean, default false)
   - Comment: "Báo cáo khẩn cấp - bỏ qua Kiểm Soát Nội Bộ"

2. **Model: SupplierSelectionReport.php**
   - Thêm `is_urgent` vào `$fillable`

3. **Resource: SupplierSelectionReportResource.php**
   - Return `is_urgent` trong API response

4. **Request: ManagerApproveSupplierSelectionReportRequest.php**
   - Thêm validation rule: `'is_urgent' => 'nullable|boolean'`

5. **Controller: SupplierSelectionReportController::managerApprove()**
   - Cập nhật `is_urgent` từ request
   - Nếu `is_urgent = true` và approved:
     - Set status = `pending_director_approval`
     - Auto-fill auditor fields
     - Log activity: `skipped_auditor`
     - Message: "Đã duyệt phiếu khẩn cấp! Phiếu đã sẵn sàng để gửi Giám đốc duyệt."

6. **Frontend: SupplierSelectionReportShow.vue**
   - Thêm Checkbox "Báo cáo khẩn cấp (bỏ qua Kiểm Soát Nội Bộ)" trong Manager approval dialog
   - Chỉ hiện khi `manager_approved_result = 'approved'`
   - Background màu cam với border để nổi bật
   - Submit `is_urgent` cùng với form data

7. **Frontend: SupplierSelectionReportIndex.vue**
   - Hiển thị badge "KHẨN CẤP" màu đỏ (severity="danger") bên cạnh mã phiếu
   - Badge hiện khi `data.is_urgent = true`

## Luồng hoạt động:

### Báo cáo thường (is_urgent = false):
Draft → Pending Manager → **Manager Approved** → Auditor Approved → Pending Director → Director Approved

### Báo cáo khẩn cấp (is_urgent = true):
Draft → Pending Manager → **Manager Approved (+ is_urgent)** → ~~Skip Auditor~~ → **Pending Director** → Director Approved

## Ưu điểm:
✅ Đơn giản, dễ sử dụng
✅ Chỉ Manager mới có quyền đánh dấu khẩn cấp
✅ Có audit trail đầy đủ
✅ Không ảnh hưởng data cũ (default = false)
✅ Badge rõ ràng trên UI
✅ Migration an toàn cho production

## Files đã tạo/sửa:
- database/migrations/2025_11_25_132831_add_is_urgent_to_supplier_selection_reports_table.php (NEW)
- app/Models/SupplierSelectionReport.php
- app/Http/Resources/SupplierSelectionReportResource.php
- app/Http/Requests/ManagerApproveSupplierSelectionReportRequest.php
- app/Http/Controllers/SupplierSelectionReportController.php
- resources/js/Pages/SupplierSelectionReportShow.vue
- resources/js/Pages/SupplierSelectionReportIndex.vue

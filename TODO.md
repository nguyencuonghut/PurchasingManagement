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

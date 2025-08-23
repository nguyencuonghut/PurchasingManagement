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

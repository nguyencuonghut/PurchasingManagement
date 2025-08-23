// Common constants and helpers for roles, statuses and severities

export const Roles = {
  ADMIN: 'Quản trị',
  PURCHASER: 'Nhân viên Thu Mua',
  PM_MANAGER: 'Trưởng phòng Thu Mua',
  AUDITOR: 'Nhân viên Kiểm Soát',
  DIRECTOR: 'Giám đốc',
};

export const Statuses = [
  'draft',
  'pending_manager_approval',
  'manager_approved',
  'auditor_approved',
  'pending_director_approval',
  'director_approved',
  'rejected',
];

export function getStatusSeverity(status) {
  switch (status) {
    case 'draft':
      return 'secondary';
    case 'pending_manager_approval':
      return 'warn';
    case 'manager_approved':
    case 'auditor_approved':
      return 'info';
    case 'pending_director_approval':
      return 'warn';
    case 'director_approved':
      return 'success';
    case 'rejected':
      return 'danger';
    default:
      return 'info';
  }
}

export function getResultSeverity(result) {
  switch (result) {
    case 'rejected':
      return 'danger';
    case 'approved':
      return 'success';
    default:
      return 'info';
  }
}

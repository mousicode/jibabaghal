<?php
namespace JBG\Roles;


class Capabilities {
public static function register(): void {
// Custom role for sponsors
if (!get_role('jbg_sponsor')) {
add_role('jbg_sponsor', __('Sponsor','jbg-core'), [
'read' => true,
]);
}
// Map capabilities for future modules
$admin_caps = [
'jbg_manage_core' => true,
'jbg_manage_ads' => true,
'jbg_manage_billing'=> true,
'jbg_view_reports' => true,
];
// Grant to administrator by default
$admin = get_role('administrator');
if ($admin) {
foreach ($admin_caps as $cap => $val) { if (!$admin->has_cap($cap)) $admin->add_cap($cap); }
}
// Minimal sponsor caps (expand later)
$sponsor = get_role('jbg_sponsor');
if ($sponsor) {
foreach (['jbg_view_reports'] as $cap) { if (!$sponsor->has_cap($cap)) $sponsor->add_cap($cap); }
}
}
}
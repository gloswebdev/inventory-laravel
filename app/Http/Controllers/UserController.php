<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Module keys for permissions
     */
    protected $modules = [
        'products' => 'Product Master',
        'recipes' => 'Recipe Master',
        'indent' => 'Production Planning',
        'planning_bulk' => 'Indent: Bulk Entry',
        'planning_process' => 'Indent: Process List',
        'production' => 'Production Manager',
        'reports' => 'Reports Viewer',
        'adjustments' => 'Stock Adjustments',
        'users' => 'User Management',
        'settings' => 'System Settings',
        'mobile_stock' => 'Mobile: Live Stock',
        'mobile_production' => 'Mobile: Production',
        'mobile_planning' => 'Mobile: Planning',
        'mobile_indents' => 'Mobile: Indents Manager',
        'mobile_recipes' => 'Mobile: Recipes',
        'mobile_adjustments' => 'Mobile: Stock Adjustments',
        'mobile_ledger' => 'Mobile: Stock Ledger',
        'mobile_products' => 'Mobile: Product Master',
        'mobile_users' => 'Mobile: User Manager',
        'mobile_settings' => 'Mobile: System Settings',
    ];

    /**
     * Specific features within modules that can be toggled
     */
    protected $moduleFeatures = [
        'reports' => [
            'display_unit' => 'Unit Toggle (KG/Units)',
            'stock_filter' => 'Zero Stock Filter',
            'search' => 'Search Bar',
            'category_filter' => 'Type/RM Category Filters',
            'branch_reorder' => 'Reorder Branch Columns'
        ],
        'mobile_stock' => [
            'display_unit' => 'Unit Toggle (KG/Units)',
            'stock_filter' => 'Zero Stock Filter',
            'branch_select' => 'Branch Selection',
            'category_filter' => 'Type/RM Category Filters',
            'search' => 'Search Bar'
        ],
        'mobile_production' => [
            'history' => 'Production History',
            'management' => 'Record New Production',
            'type_filter' => 'Type Filter'
        ],
        'indent' => [
            'bulk_add' => 'Bulk Add Modal',
            'branch_select' => 'Stock Location Toggle',
            'type_filter' => 'Type Filter',
            'clone' => 'Clone Indent'
        ],
        'production' => [
            'history' => 'View Production History',
            'management' => 'Record New Production',
            'type_filter' => 'Type Filter'
        ],
        'mobile_planning' => [
            'type_filter' => 'Product Type Filter',
            'branch_select' => 'Branch Selection'
        ],
        'mobile_indents' => [
            'bulk_entry' => 'New Indent (Bulk)',
            'history' => 'Transaction History',
            'process' => 'Comparison View',
            'user_filter' => 'Multi-User Filter',
            'unit_toggle' => 'BOX/KG Unit Toggle',
            'clone' => 'Clone Indent',
            'delete' => 'Delete Indent',
            'edit' => 'Edit Indent',
            'branch_reorder' => 'Reorder Branch Columns'
        ],
        'mobile_recipes' => [
            'view' => 'View Recipes',
            'search' => 'Search Recipes',
            'edit' => 'Edit Recipes',
            'delete' => 'Delete Recipes'
        ],
        'mobile_adjustments' => [
            'view' => 'View Adjustments',
            'create' => 'Record Adjustment'
        ],
        'mobile_ledger' => [
            'view' => 'View Ledger',
            'search' => 'Search Logs'
        ],
        'mobile_products' => [
            'view' => 'View Products',
            'edit' => 'Edit Basics',
            'sync' => 'API Sync'
        ],
        'mobile_users' => [
            'view' => 'View Users',
            'create' => 'Add New User',
            'edit' => 'Update Permissions'
        ],
        'mobile_settings' => [
            'management' => 'Branch Mapping Management'
        ]
    ];

    public function index(Request $request)
    {
        $currentType = $request->get('type');
        $query = User::with(['permissions', 'branches', 'productTypes', 'permittedAttributes']);
        
        if ($currentType) {
            $query->where('interface_type', $currentType);
        }

        $users = $query->get();
        $branches = \App\Models\Branch::orderBy('name')->get();
        $productTypes = \App\Models\ProductType::orderBy('type_name')->get();
        $rmTypes = \App\Models\ProductAttribute::where('type', 'rm_type')->orderBy('value')->get();

        return view('users.index', [
            'users' => $users,
            'modules' => $this->modules,
            'moduleFeatures' => $this->moduleFeatures,
            'branches' => $branches,
            'productTypes' => $productTypes,
            'rmTypes' => $rmTypes,
            'currentType' => $currentType
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'role' => ['required', 'in:user,admin'],
            'interface_type' => ['required', 'in:desktop,mobile'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'role' => $request->role,
                'interface_type' => $request->interface_type,
                'password' => Hash::make($request->password),
            ]);

            if ($request->has('branches')) {
                $user->branches()->sync($request->branches);
            }

            if ($request->has('product_types')) {
                $user->productTypes()->sync($request->product_types);
            }

            if ($request->has('rm_types')) {
                $user->permittedAttributes()->sync($request->rm_types);
            }

            if ($request->has('permissions')) {
                foreach ($request->permissions as $pageKey => $rights) {
                    UserPermission::create([
                        'user_id' => $user->id,
                        'page_key' => $pageKey,
                        'can_view' => isset($rights['view']),
                        'can_create' => isset($rights['create']),
                        'can_edit' => isset($rights['edit']),
                        'can_delete' => isset($rights['delete']),
                        'can_print' => isset($rights['print']),
                        'can_export_excel' => isset($rights['excel']),
                        'can_export_pdf' => isset($rights['pdf']),
                        'features' => $rights['features'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $user->id],
            'role' => ['required', 'in:user,admin'],
            'interface_type' => ['required', 'in:desktop,mobile'],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        DB::transaction(function () use ($request, $user) {
            $user->update([
                'name' => $request->name,
                'username' => $request->username,
                'role' => $request->role,
                'interface_type' => $request->interface_type,
            ]);

            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($request->password)]);
            }

            if ($request->has('branches')) {
                $user->branches()->sync($request->branches);
            } else {
                $user->branches()->sync([]);
            }

            if ($request->has('product_types')) {
                $user->productTypes()->sync($request->product_types);
            } else {
                $user->productTypes()->sync([]);
            }

            if ($request->has('rm_types')) {
                $user->permittedAttributes()->sync($request->rm_types);
            } else {
                $user->permittedAttributes()->sync([]);
            }

            // Sync Permissions
            $user->permissions()->delete();
            if ($request->has('permissions')) {
                foreach ($request->permissions as $pageKey => $rights) {
                    UserPermission::create([
                        'user_id' => $user->id,
                        'page_key' => $pageKey,
                        'can_view' => isset($rights['view']),
                        'can_create' => isset($rights['create']),
                        'can_edit' => isset($rights['edit']),
                        'can_delete' => isset($rights['delete']),
                        'can_print' => isset($rights['print']),
                        'can_export_excel' => isset($rights['excel']),
                        'can_export_pdf' => isset($rights['pdf']),
                        'features' => $rights['features'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'You cannot delete yourself.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function togglePermission(Request $request, User $user)
    {
        $pageKey = $request->page_key;
        $type = $request->type; // can_view, can_create, etc.
        $value = $request->value;

        $permission = UserPermission::updateOrCreate(
            ['user_id' => $user->id, 'page_key' => $pageKey],
            [$type => $value]
        );

        return response()->json(['success' => true]);
    }
}

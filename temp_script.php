<?php
foreach(\App\Models\User::with('role')->get() as $u) {
    echo "ID: " . $u->id . " | Email: " . $u->email . " | Password: password | Rol: " . ($u->role ? $u->role->name : 'N/A') . "\n";
}

<?php 

namespace nexpell;

class RoleManager {

    /*public static function getUserRoleID(int $userID): ?int {
        global $_database;

        $stmt = $_database->prepare("SELECT roleID FROM user_role_assignments WHERE userID = ?");
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $stmt->bind_result($roleID);

        if ($stmt->fetch()) {
            return $roleID;
        }

        return null;
    }*/

    public static function getUserRoleIDs(int $userID): array {
        global $_database;

        $stmt = $_database->prepare("SELECT roleID FROM user_role_assignments WHERE userID = ?");
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $result = $stmt->get_result();

        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = (int)$row['roleID'];
        }
        return $roles;
    }

    public static function roleHasPermission(int $roleID, string $permission_key): bool {
        global $_database;

        $stmt = $_database->prepare("SELECT 1 FROM user_role_permissions WHERE roleID = ? AND permission_key = ?");
        $stmt->bind_param("is", $roleID, $permission_key);
        $stmt->execute();
        $stmt->store_result();

        return $stmt->num_rows > 0;
    }

    /*public static function getUserRoles(int $userID): array {
        global $_database;

        $stmt = $_database->prepare("
            SELECT r.role_name
            FROM user_role_assignments ura
            JOIN user_roles r ON ura.roleID = r.roleID
            WHERE ura.userID = ?
        ");
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $result = $stmt->get_result();

        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = $row['role_name'];
        }
        return $roles;
    }*/

    public static function userHasRole(int $userID, int $roleID): bool {
        global $_database;

        $stmt = $_database->prepare("
            SELECT 1
            FROM user_role_assignments
            WHERE userID = ? AND roleID = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $userID, $roleID);
        $stmt->execute();
        $stmt->store_result();

        return $stmt->num_rows > 0;
    }
}
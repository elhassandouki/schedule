# Intégration spatie/laravel-permission — Plan

## État actuel
- Champ `role` custom sur User (super_admin, sous_admin, chef_departement, chef_filiere, prof)
- Middleware EnsureRole custom
- Register : rôle par défaut 'prof', pas de choix de rôle

## Décisions
1. Garder le champ `role` (compat) mais utiliser spatie pour roles/permissions :
   - Synchroniser le trait HasRoles sur User, faire de `role` un accessor vers le rôle spatie OU
   - Choix retenu : spatie devient la source de vérité ; on garde le champ role rempli en parallèle
     (plus simple, moins de refactoring des vues). À chaque création/mise à jour d'un utilisateur,
     assigner le rôle spatie correspondant, et $user->role reste un getter basé sur spatie.
2. Migrations publiées : database/migrations/2026_08_13_231306_create_permission_tables.php
3. Seeding : database/seeders/RolePermissionSeeder.php
   - Permissions : view|create|update|delete pour :
     departements, filieres, semestres, salles, modules, groupes, professeurs, sessions, generations, users
   - Roles et perms :
     - super_admin : tout
     - sous_admin : tout sauf users management (gérer utilisateurs : voir+update)
     - chef_departement : CRUD filières, semestres, modules, groupes, professeurs (dépt), sessions, générations
     - chef_filiere : semestres, modules, groupes, professeurs (filière), sessions, générations
     - prof : voir sessions, voir générations, consulter emplois (timetable.show, exports)
4. Middleware : remplacer EnsureRole par une implémentation basée sur $user->hasRole(...), alias 'role'
5. RegisterController : champ role optionnel uniquement si le register est admin (garder prof par défaut,
   ajouter select role seulement accessible côté admin via une page dédiée de gestion utilisateurs)
   -> Plus propre : ajouter page 'gestion/utilisateurs' avec CRUD des rôles (assignRole) pour super_admin/sous_admin
6. Views : @can directives dans sidebar (menu adminlte a un support natif: 'can' => 'view filieres')
7. Tests : 79 tests verts, ré-exécuter après modifications

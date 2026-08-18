# Analyse du PDF emploi_du_temps_semestre_1_2(1).pdf (18/08/2026)

## Problème observé : la salle change TOUJOURS entre créneaux consécutifs
- Lundi 09:00 : Biologie cellulaire → Salle 02
- Lundi 11:00 : Biologie cellulaire → Salle 03 (CHANGEMENT !)
- Lundi 14:00 : Histologie → Salle 04
- Lundi 16:00 : Histologie → Salle 05 (CHANGEMENT !)
- Mardi 09:00 : Géologie → Salle 06
- Mardi 11:00 : Géologie → Salle 07 (CHANGEMENT !)
- Mardi 14:00 : Mathématiques → Salle 08
- Mardi 16:00 : Mathématiques → Salle 09 (CHANGEMENT !)
- Mercredi 09:00 : Physique → Salle 10
- Mercredi 11:00 : Physique → Salle 11 (CHANGEMENT !)
- Mercredi 14:00 : Chimie → Salle 12
- Mercredi 16:00 : Chimie → Salle 12 (! cette fois même salle, ou erreur?)
  → Chimie semble stable à Salle 12 (la dernière). Physique 10→11 = instable.

## Pattern : chaque module garde une salle DIFFÉRENTE pour chaque session
→ Le module reçoit une salle NOUVELLE à chaque session (02, 03, 04, 05...).

## Hypothèse de cause
Le code local de l'utilisateur NE CONTIENT PAS encore la fix (commit 2f9c70a).
OU : la table timetable_sessions contient des sessions d'une ancienne génération
qui n'ont PAS été supprimées → overlaps('classroom_id', assigned, ...) retourne true
car une SESSION DU MÊME MODULE existe déjà dans la même salle (le générateur
considère sa propre session comme un conflit ? non, c'est le MÊME module/groupe,
mais overlaps vérifie uniquement classroom_id + day_id + horaires — il ne voit pas
le module).
AH ! BUG RÉEL : overlaps('classroom_id', assignedRoomId, ...) cherche toute session
sur la salle, PEU IMPORTE le module. Si la session 1 du module a été placée en Salle 02
à 09:00, au créneau 11:00 overlaps(Salle 02, 11:00-12:30) → 10:30 < 12:30 ET 12:30 > 11:00
→ chevauchement = (10:30 < 12:30) ET (10:30 > 11:00) = FAUX. Pas de chevauchement.

## Nouvelle hypothèse : la requête overlaps utilise les timeslots de TOUS les semestres
Non, le filtre day_id limite.

## Hypothèse probable (à vérifier) : le créneau "11:00-12:30" a starts_at='11:00',
ends_at='12:30'. overlaps(Salle 02, lundi, 660, 750):
  t.starts_at < '12:30' AND t.ends_at > '11:00'
  Session existante : starts_at='09:00', ends_at='10:30'
  '09:00' < '12:30' = TRUE
  '10:30' > '11:00' = FALSE
  → overlaps = FALSE → candidate = Salle 02.

DONC AVEC LE NOUVEAU CODE, la salle doit être stable.
→ L'utilisateur tourne encore sur l'ANCIENNE version (avant 2f9c70a).
Il faut lui demander de vérifier qu'il a bien fait :
  git pull origin main
  (et éventuellement php artisan config:clear, composer dump-autoload, et relancer php artisan serve)

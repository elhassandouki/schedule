<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Garantie dure côté MySQL : une salle (ou un prof, ou un groupe) ne peut jamais
 * accueillir deux sessions dont les horaires se CHEVAUCHENT le même jour, même
 * si les deux créneaux sont des timeslot_id différents (ex : 16:00-17:30 et
 * 16:30-18:00).
 *
 * Des déclencheurs "avant insertion" sur timetable_sessions lèvent une erreur
 * SQL explicite (1644) si une session existante utilise la même salle, le même
 * prof ou le même groupe sur le même jour et le même semestre avec des horaires
 * qui chevauchent le créneau candidat.
 *
 * SQLite : les triggers ne peuvent pas lever d'erreur propre (pas de SIGNAL) ;
 * la protection est assurée par la logique applicative (AutoGenerateTimetable
 * et SessionConflictChecker utilisent déjà la détection par chevauchement
 * horaire) et par les contraintes uniques sur timeslot_id identique.
 */
return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') return;

        foreach ([
            'timetable_sessions_bi_room_overlap' => 'classroom_id',
            'timetable_sessions_bi_prof_overlap' => 'professor_id',
            'timetable_sessions_bi_group_overlap' => 'student_group_id',
        ] as $name => $column) {
            DB::statement("DROP TRIGGER IF EXISTS {$name}");
        }

        DB::statement("
            CREATE TRIGGER timetable_sessions_bi_room_overlap
            BEFORE INSERT ON timetable_sessions
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM timetable_sessions AS s
                    INNER JOIN timeslots AS t ON t.id = s.timeslot_id
                    WHERE s.classroom_id = NEW.classroom_id
                      AND s.day_id = NEW.day_id
                      AND s.semester_id = NEW.semester_id
                      AND t.starts_at < (SELECT ends_at FROM timeslots WHERE id = NEW.timeslot_id)
                      AND t.ends_at > (SELECT starts_at FROM timeslots WHERE id = NEW.timeslot_id)
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conflit : cette salle est déjà occupée dans ce créneau.';
                END IF;
            END
        ");

        DB::statement("
            CREATE TRIGGER timetable_sessions_bi_prof_overlap
            BEFORE INSERT ON timetable_sessions
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM timetable_sessions AS s
                    INNER JOIN timeslots AS t ON t.id = s.timeslot_id
                    WHERE s.professor_id = NEW.professor_id
                      AND s.day_id = NEW.day_id
                      AND s.semester_id = NEW.semester_id
                      AND t.starts_at < (SELECT ends_at FROM timeslots WHERE id = NEW.timeslot_id)
                      AND t.ends_at > (SELECT starts_at FROM timeslots WHERE id = NEW.timeslot_id)
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conflit : ce professeur est déjà occupé dans ce créneau.';
                END IF;
            END
        ");

        DB::statement("
            CREATE TRIGGER timetable_sessions_bi_group_overlap
            BEFORE INSERT ON timetable_sessions
            FOR EACH ROW
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM timetable_sessions AS s
                    INNER JOIN timeslots AS t ON t.id = s.timeslot_id
                    WHERE s.student_group_id = NEW.student_group_id
                      AND s.day_id = NEW.day_id
                      AND s.semester_id = NEW.semester_id
                      AND t.starts_at < (SELECT ends_at FROM timeslots WHERE id = NEW.timeslot_id)
                      AND t.ends_at > (SELECT starts_at FROM timeslots WHERE id = NEW.timeslot_id)
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conflit : ce groupe est déjà occupé dans ce créneau.';
                END IF;
            END
        ");
    }

    public function down(): void
    {
        foreach ([
            'timetable_sessions_bi_room_overlap',
            'timetable_sessions_bi_prof_overlap',
            'timetable_sessions_bi_group_overlap',
        ] as $name) {
            DB::statement("DROP TRIGGER IF EXISTS {$name}");
        }
    }
};

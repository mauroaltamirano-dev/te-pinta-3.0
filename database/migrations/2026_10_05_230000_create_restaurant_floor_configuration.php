<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->integer('width')->default(1600);
            $table->integer('height')->default(1000);
            $table->integer('layout_version')->default(0);
            $table->integer('layout_schema_version')->default(1);
            $table->jsonb('obstacles')->default('[]');
            $table->timestamps();
            $table->index(['is_active', 'id']);
        });
        Schema::create('dining_tables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dining_area_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name', 100)->nullable();
            $table->string('shape', 12);
            $table->decimal('position_x', 5, 4);
            $table->decimal('position_y', 5, 4);
            $table->decimal('width', 5, 4);
            $table->decimal('height', 5, 4);
            $table->smallInteger('rotation')->default(0);
            $table->smallInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['dining_area_id', 'is_active', 'id']);
        });
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION restaurant_geometry_valid(x numeric, y numeric, w numeric, h numeric, rotation integer, aw integer, ah integer, shape text)
            RETURNS boolean LANGUAGE sql IMMUTABLE STRICT AS $$
                WITH coefficients AS (
                    SELECT CASE WHEN shape = 'circle' OR rotation % 180 = 0 THEN 10000 WHEN rotation % 180 = 90 THEN 0 ELSE 7072 END::numeric AS c,
                           CASE WHEN shape = 'circle' OR rotation % 180 = 0 THEN 0 WHEN rotation % 180 = 90 THEN 10000 ELSE 7072 END::numeric AS s
                )
                SELECT shape IN ('rectangle', 'circle') AND rotation IN (0,45,90,135,180,225,270,315)
                    AND aw BETWEEN 100 AND 10000 AND ah BETWEEN 100 AND 10000
                    AND x >= 0 AND y >= 0 AND w > 0 AND h > 0 AND x+w <= 1 AND y+h <= 1
                    AND (shape <> 'circle' OR abs(w*aw-h*ah) <= 1)
                    AND (2*x+w)*10000*aw >= c*w*aw+s*h*ah
                    AND (2*x+w)*10000*aw+c*w*aw+s*h*ah <= 20000*aw
                    AND (2*y+h)*10000*ah >= s*w*aw+c*h*ah
                    AND (2*y+h)*10000*ah+s*w*aw+c*h*ah <= 20000*ah
                FROM coefficients
            $$;

            CREATE OR REPLACE FUNCTION restaurant_obstacles_valid(elements jsonb, aw integer, ah integer)
            RETURNS boolean LANGUAGE plpgsql IMMUTABLE STRICT AS $$
            DECLARE element jsonb; key text;
            BEGIN
                IF jsonb_typeof(elements) <> 'array' OR jsonb_array_length(elements) > 100 THEN RETURN false; END IF;
                FOR element IN SELECT value FROM jsonb_array_elements(elements) LOOP
                    IF jsonb_typeof(element) <> 'object' THEN RETURN false; END IF;
                    FOR key IN SELECT jsonb_object_keys(element) LOOP
                        IF key NOT IN ('type','x','y','width','height','label') THEN RETURN false; END IF;
                    END LOOP;
                    IF NOT (element ?& ARRAY['type','x','y','width','height']) OR jsonb_typeof(element->'type') <> 'string' THEN RETURN false; END IF;
                    IF element ? 'label' AND jsonb_typeof(element->'label') NOT IN ('string','null') THEN RETURN false; END IF;
                    IF length(element->>'label') > 100 THEN RETURN false; END IF;
                    FOREACH key IN ARRAY ARRAY['x','y','width','height'] LOOP
                        IF jsonb_typeof(element->key) <> 'string' OR (element->>key) !~ '^(0(\.[0-9]{1,4})?|1(\.0{1,4})?)$' THEN RETURN false; END IF;
                    END LOOP;
                    IF NOT restaurant_geometry_valid((element->>'x')::numeric,(element->>'y')::numeric,(element->>'width')::numeric,(element->>'height')::numeric,0,aw,ah,element->>'type') THEN RETURN false; END IF;
                END LOOP;
                RETURN true;
            END $$;

            ALTER TABLE dining_areas ADD CONSTRAINT dining_areas_configuration_check CHECK (
                length(btrim(name)) > 0 AND width BETWEEN 100 AND 10000 AND height BETWEEN 100 AND 10000
                AND layout_version >= 0 AND layout_schema_version = 1
                AND restaurant_obstacles_valid(obstacles, width, height)
            );
            ALTER TABLE dining_tables ADD CONSTRAINT dining_tables_configuration_check CHECK (
                length(btrim(code)) > 0 AND code = upper(btrim(code)) AND shape IN ('rectangle','circle')
                AND position_x BETWEEN 0 AND 1 AND position_y BETWEEN 0 AND 1
                AND width > 0 AND height > 0 AND position_x+width <= 1 AND position_y+height <= 1
                AND rotation IN (0,45,90,135,180,225,270,315) AND (capacity IS NULL OR capacity BETWEEN 1 AND 100)
            );
            ALTER TABLE dining_tables ADD CONSTRAINT dining_tables_area_code_unique UNIQUE (dining_area_id, code) DEFERRABLE INITIALLY DEFERRED;

            CREATE OR REPLACE FUNCTION restaurant_validate_final_table_bounds() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE area_id bigint;
            BEGIN
                IF TG_TABLE_NAME = 'dining_areas' THEN area_id := NEW.id; ELSE area_id := NEW.dining_area_id; END IF;
                IF EXISTS (
                    SELECT 1 FROM dining_tables t JOIN dining_areas a ON a.id = t.dining_area_id
                    WHERE a.id = area_id AND NOT restaurant_geometry_valid(t.position_x,t.position_y,t.width,t.height,t.rotation,a.width,a.height,t.shape)
                ) THEN RAISE EXCEPTION 'Dining table rotated footprint must remain within its area' USING ERRCODE = '23514'; END IF;
                RETURN NULL;
            END $$;
            CREATE CONSTRAINT TRIGGER dining_tables_final_bounds AFTER INSERT OR UPDATE ON dining_tables DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION restaurant_validate_final_table_bounds();
            CREATE CONSTRAINT TRIGGER dining_areas_final_bounds AFTER UPDATE ON dining_areas DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION restaurant_validate_final_table_bounds();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('dining_areas');
        DB::unprepared('DROP FUNCTION restaurant_validate_final_table_bounds(); DROP FUNCTION restaurant_obstacles_valid(jsonb, integer, integer); DROP FUNCTION restaurant_geometry_valid(numeric, numeric, numeric, numeric, integer, integer, integer, text);');
    }
};

SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;


CREATE SCHEMA types;


ALTER SCHEMA types OWNER TO maf;

SET search_path = public, pg_catalog, public;


CREATE FUNCTION postgis_libjson_version() RETURNS text
    LANGUAGE c IMMUTABLE STRICT
    AS '/Library/PostgreSQL/9.2/lib/postgis-2.0', 'postgis_libjson_version';


ALTER FUNCTION public.postgis_libjson_version() OWNER TO postgres;


CREATE FUNCTION postgis_svn_version() RETURNS text
    LANGUAGE c IMMUTABLE
    AS '/Library/PostgreSQL/9.2/lib/postgis-2.0', 'postgis_svn_version';


ALTER FUNCTION public.postgis_svn_version() OWNER TO postgres;


COMMENT ON FUNCTION st_asewkt(geography) IS 'args: g1 - Return the Well-Known Text (WKT) representation of the geometry with SRID meta data.';



CREATE FUNCTION st_asewkt(text) RETURNS text
    LANGUAGE sql IMMUTABLE STRICT
    AS $_$ SELECT ST_AsEWKT($1::geometry);  $_$;


ALTER FUNCTION public.st_asewkt(text) OWNER TO postgres;


COMMENT ON FUNCTION st_collectionhomogenize(geometry) IS 'args: collection - Given a geometry collection, returns the "simplest" representation of the contents.';



COMMENT ON FUNCTION st_interpolatepoint(line geometry, point geometry) IS 'args: line, point - Return the value of the measure dimension of a geometry at the point closed to the provided point.';



COMMENT ON FUNCTION st_locatealong(geometry geometry, measure double precision, leftrightoffset double precision) IS 'args: ageom_with_measure, a_measure, offset - Return a derived geometry collection value with elements that match the specified measure. Polygonal elements are not supported.';



COMMENT ON FUNCTION st_locatebetween(geometry geometry, frommeasure double precision, tomeasure double precision, leftrightoffset double precision) IS 'args: geomA, measure_start, measure_end, offset - Return a derived geometry collection value with elements that match the specified range of measures inclusively. Polygonal elements are not supported.';



COMMENT ON FUNCTION st_rotate(geometry, double precision, geometry) IS 'args: geomA, rotRadians, pointOrigin - Rotate a geometry rotRadians counter-clockwise about an origin.';



COMMENT ON FUNCTION st_rotate(geometry, double precision, double precision, double precision) IS 'args: geomA, rotRadians, x0, y0 - Rotate a geometry rotRadians counter-clockwise about an origin.';



COMMENT ON FUNCTION st_summary(geography) IS 'args: g - Returns a text summary of the contents of the geometry.';



COMMENT ON FUNCTION updategeometrysrid(catalogn_name character varying, schema_name character varying, table_name character varying, column_name character varying, new_srid_in integer) IS 'args: catalog_name, schema_name, table_name, column_name, srid - Updates the SRID of all features in a geometry column, geometry_columns metadata and srid table constraint';





ALTER OPERATOR public.&& (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.&& (geography, geography) OWNER TO postgres;




ALTER OPERATOR public.&&& (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.&< (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.&<| (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.&> (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.< (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.< (geography, geography) OWNER TO postgres;


CREATE OPERATOR <#> (
    PROCEDURE = geometry_distance_box,
    LEFTARG = geometry,
    RIGHTARG = geometry,
    COMMUTATOR = <#>
);


ALTER OPERATOR public.<#> (geometry, geometry) OWNER TO postgres;


CREATE OPERATOR <-> (
    PROCEDURE = geometry_distance_centroid,
    LEFTARG = geometry,
    RIGHTARG = geometry,
    COMMUTATOR = <->
);


ALTER OPERATOR public.<-> (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.<< (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.<<| (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.<= (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.<= (geography, geography) OWNER TO postgres;




ALTER OPERATOR public.= (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.= (geography, geography) OWNER TO postgres;




ALTER OPERATOR public.> (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.> (geography, geography) OWNER TO postgres;




ALTER OPERATOR public.>= (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.>= (geography, geography) OWNER TO postgres;




ALTER OPERATOR public.>> (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.@ (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.|&> (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.|>> (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.~ (geometry, geometry) OWNER TO postgres;




ALTER OPERATOR public.~= (geometry, geometry) OWNER TO postgres;


REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;

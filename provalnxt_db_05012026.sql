-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 04, 2026 at 07:45 PM
-- Server version: 9.2.0
-- PHP Version: 8.4.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `provalnxt_demo`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_schedule` (IN `_unit_id` INT, IN `_schedule_year` VARCHAR(4), OUT `returnmsg` VARCHAR(100))   proc_label:begin

DECLARE finished INTEGER DEFAULT 0;

Declare _eq_id int;
Declare _val_wf_id varchar(45);
Declare _val_freq varchar(45);
Declare _test_id int;
Declare _val_wf_planned_start_date date;
Declare _val_wf_actual_start_datetime datetime; 
Declare _test_conducted_date date;
Declare _equip_add_date date;
Declare sch_year varchar(4) ;

-- declare cursor for temp table
	DEClARE curTempTable 
		CURSOR FOR 
			 SELECT * FROM new_tbl;
  


-- declare cursor for temp table
	DEClARE curTempTable_n 
		CURSOR FOR 
			 SELECT * FROM new_tbl_n;

-- declare NOT FOUND handler
	DECLARE CONTINUE HANDLER 
        FOR NOT FOUND SET finished = 1;  
  


-- Stage 1: Whether all the scheduled validations are complete
-- To continue only if all the scheduled validations are complete. Otherwise, exit.

select count(*) into @sch_val_count from tbl_val_schedules where unit_id=_unit_id and year(val_wf_planned_start_date)=cast((cast(_schedule_year as unsigned)-1) as char(4))  and val_wf_status='Active';
select count(*) into @com_val_count from tbl_val_wf_tracking_details where val_wf_current_stage='5' and unit_id=_unit_id;


if @sch_val_count<>@com_val_count then
	SET @val_wf_schedule_id=NULL;
    SELECT 'current_year_sch_pending' as returnmessage;
   SET returnmsg='current_year_sch_pending';
    leave proc_label;
end if;

if @sch_val_count=@com_val_count then

	select count(*) into @test_completed_count from tbl_test_schedules_tracking where test_id=4 and unit_id=_unit_id and val_wf_id in 
	(select val_wf_id from tbl_val_schedules where unit_id=_unit_id and year(val_wf_planned_start_date)=cast((cast(_schedule_year as unsigned)-1) as char(4))  and val_wf_status='Active');

	if @test_completed_count <> @com_val_count then
		SELECT 'current_year_sch_test_pending' as returnmessage;
        SET returnmsg='current_year_sch_test_pending';
		leave proc_label;
    end if;


end if;





select max(schedule_year) into @current_high_year from tbl_val_wf_schedule_requests where unit_id=_unit_id;

SET finished = 0; -- hence reset this value for cursor.

if @current_high_year is not null && cast(@current_high_year as unsigned) >= cast(_schedule_year as unsigned) then
	
    SELECT 'already_exists' as returnmessage;
    SET returnmsg='already_exists';
    leave proc_label;

end if;

if @current_high_year is not null && (cast(_schedule_year as unsigned) >= (cast(@current_high_year as unsigned) + 1)) then
	select 'invalid_year'  as returnmessage;
    SET returnmsg='invalid_year';
    leave proc_label;

end if;




-- select schedule_year into sch_year from tbl_val_wf_schedule_requests where schedule_year=_schedule_year;

-- SET finished = 0; -- hence reset this value for cursor.

-- if sch_year is not null then
	 -- SELECT 'already_exists' as returnmessage;
    -- leave proc_label;
-- end if;

insert into tbl_val_wf_schedule_requests (schedule_year,unit_id) values (_schedule_year,_unit_id);

SELECT LAST_INSERT_ID() into @schedule_id;
SET finished = 0; -- hence reset this value for cursor.



-- Stage 2: Find the latest date on which the principle test was conducted for an equipment in the given year
DROP TABLE IF EXISTS new_tbl;
CREATE TEMPORARY TABLE new_tbl select t1.equipment_id,t1.val_wf_id,t4.validation_frequency,t2.test_id,t3.val_wf_planned_start_date,t1.actual_wf_start_datetime,t2.test_conducted_date
from tbl_val_wf_tracking_details t1, tbl_test_schedules_tracking t2,tbl_val_schedules t3, equipments t4
where t1.val_wf_id=t2.val_wf_id and t2.test_id=4 and t1.unit_id=_unit_id and year(t1.actual_wf_start_datetime)=cast((cast(_schedule_year as unsigned)-1) as char(4)) 
and t1.val_wf_id=t3.val_wf_id
and t1.equipment_id=t4.equipment_id
and (t2.equip_id,t2.test_conducted_date) in (
select equip_id, max(test_conducted_date) from tbl_test_schedules_tracking where test_id=4 and unit_id=_unit_id group by equip_id );

select * from new_tbl;
SET finished = 0; -- hence reset this value for cursor.

-- select * from new_tbl;
open curTempTable;


read_loop: LOOP

  -- And then fetch
  fetch   curTempTable into _eq_id, _val_wf_id,_val_freq,_test_id,_val_wf_planned_start_date,_val_wf_actual_start_datetime, _test_conducted_date;
  -- And then, if no row is fetched, exit the loop
 -- select 'Cursor opened';

  
IF finished THEN 
			LEAVE read_loop;
		END IF;
     
 
 

  if _val_freq='M' THEN
  CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 1 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 1 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 2 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 2 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 3 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 3 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 4 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 4 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 5 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 5 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 6 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 6 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 7 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 7 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 8 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 8 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 9 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 9 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 10 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 10 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 11 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 11 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 12 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 12 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
--  leave proc_label;
SET finished = 0; -- hence reset this value for cursor.
SELECT 'success' as returnmessage;
   SET returnmsg='success';
  
  END IF;
 
  if _val_freq='Q' THEN
  
 
	 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id_q1);
	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id_q1,'-Q1'),DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 3 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 3 month),INTERVAL 1 DAY), INTERVAL 40 DAY));

	 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id_q2);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id_q2,'-Q2'),DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 6 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 6 month),INTERVAL 1 DAY), INTERVAL 40 DAY));


	 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id_q3);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id_q3,'-Q3'),DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 9 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 9 month),INTERVAL 1 DAY), INTERVAL 40 DAY));

	 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id_q4);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id_q4,'-Q4'),DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 12 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 12 month),INTERVAL 1 DAY), INTERVAL 40 DAY));


   SELECT 'success' as returnmessage;
   SET returnmsg='success';
  
  -- leave proc_label;
SET finished = 0; -- hence reset this value for cursor.
  
  
  END IF;
  
  if _val_freq='H' THEN

	 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id,'-H1'),DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 6 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 6 month),INTERVAL 1 DAY), INTERVAL 40 DAY));

 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);
insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
  values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id,'-H2'),DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 1 year),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 12 month),INTERVAL 1 DAY), INTERVAL 40 DAY));

 SELECT 'success' as returnmessage;
 SET returnmsg='success';
  
  -- leave proc_label;
SET finished = 0; -- hence reset this value for cursor.
  
  
  END IF;
  
  if _val_freq='Y' THEN
  
 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);
insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
   values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id,'-Y'),DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 1 year),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 1 year),INTERVAL 1 DAY), INTERVAL 40 DAY));
SELECT 'success' as returnmessage;
SET returnmsg='success';
  
SET finished = 0; -- hence reset this value for cursor.

--  leave proc_label;
  END IF;
  
  if _val_freq='2Y' THEN
	
 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);
insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id,'-2Y'),DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 2 year),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(_test_conducted_date, INTERVAL 2 year),INTERVAL 1 DAY), INTERVAL 40 DAY));
SELECT 'success' as returnmessage;
SET returnmsg='success';
  
SET finished = 0; -- hence reset this value for cursor.


  END IF;
   
  
  
END LOOP;

 close curTempTable; 





-- Stage 2: Find the latest date on which the principle test was conducted for an equipment in the given year
DROP TABLE IF EXISTS new_tbl_n;
CREATE TEMPORARY TABLE new_tbl_n select equipment_id,validation_frequency,equipment_addition_date from equipments where unit_id=_unit_id and equipment_status='Active'
and equipment_id not in (select distinct equip_id from tbl_val_schedules where unit_id=_unit_id and val_wf_status='Active');

SET finished = 0; -- hence reset this value for cursor.

select * from new_tbl_n;
open curTempTable_n;

--  select 'Cursor opened';

read_loop: LOOP

  -- And then fetch
  fetch   curTempTable_n into _eq_id, _val_freq,_equip_add_date;
  -- And then, if no row is fetched, exit the loop
--  select 'Cursor opened';

  
IF finished THEN 
			LEAVE read_loop;
		END IF;
     
-- SELECT EXTRACT(day FROM "2017-06-15 09:34:21");
select DAYOFYEAR(_equip_add_date) into @yearday;
-- select extract(year from @yearday) into @yr;
SELECT MAKEDATE(cast((cast(_schedule_year as unsigned)-1) as char(4)),@yearday) into @new_date;
 

  if _val_freq='M' THEN
  CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 1 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 1 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 2 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 2 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 3 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 3 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 4 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 4 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 5 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 5 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 6 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 6 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 7 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 7 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 8 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 8 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 9 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 9 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 10 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 10 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 11 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 11 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
   CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,@equip_id,@val_wf_id,DATE_SUB(DATE_ADD(@new_date, INTERVAL 12 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 12 month),INTERVAL 1 DAY), INTERVAL 40 DAY));
  
--  leave proc_label;
SET finished = 0; -- hence reset this value for cursor.
SELECT 'success' as returnmessage;
  SET returnmsg='success';
  
  
  END IF;
 
  if _val_freq='Q' THEN
  
 
	 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id_q1);
	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id_q1,'-Q1'),DATE_SUB(DATE_ADD(@new_date, INTERVAL 3 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 3 month),INTERVAL 1 DAY), INTERVAL 40 DAY));

	 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id_q2);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id_q2,'-Q2'),DATE_SUB(DATE_ADD(@new_date, INTERVAL 6 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 6 month),INTERVAL 1 DAY), INTERVAL 40 DAY));


	 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id_q3);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id_q3,'-Q3'),DATE_SUB(DATE_ADD(@new_date, INTERVAL 9 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 9 month),INTERVAL 1 DAY), INTERVAL 40 DAY));

	 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id_q4);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id_q4,'-Q4'),DATE_SUB(DATE_ADD(@new_date, INTERVAL 12 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 12 month),INTERVAL 1 DAY), INTERVAL 40 DAY));


   SELECT 'success' as returnmessage;
   SET returnmsg='success';
  
  -- leave proc_label;
SET finished = 0; -- hence reset this value for cursor.
  
  
  END IF;
  
  if _val_freq='H' THEN

	 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);

	insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id,'-H1'),DATE_SUB(DATE_ADD(@new_date, INTERVAL 6 month),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 6 month),INTERVAL 1 DAY), INTERVAL 40 DAY));

 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);
insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
  values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id,'-H2'),DATE_SUB(DATE_ADD(@new_date, INTERVAL 1 year),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 12 month),INTERVAL 1 DAY), INTERVAL 40 DAY));

 SELECT 'success' as returnmessage;
 SET returnmsg='success';
  
  -- leave proc_label;
SET finished = 0; -- hence reset this value for cursor.
  
  
  END IF;
  
  if _val_freq='Y' THEN
  
 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);
insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
   values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id,'-Y'),DATE_SUB(DATE_ADD(@new_date, INTERVAL 1 year),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 1 year),INTERVAL 1 DAY), INTERVAL 40 DAY));
SELECT 'success' as returnmessage;
SET returnmsg='success';
  
SET finished = 0; -- hence reset this value for cursor.

--  leave proc_label;
  END IF;
  
  if _val_freq='2Y' THEN
	
 CALL `generate_val_wf_id`(_eq_id,_unit_id, @val_wf_id);
insert into tbl_proposed_val_schedules (schedule_id,unit_id,equip_id,val_wf_id,val_wf_planned_start_date,val_wf_planned_end_date)  
    values (@schedule_id,_unit_id,_eq_id,concat(@val_wf_id,'-2Y'),DATE_SUB(DATE_ADD(@new_date, INTERVAL 2 year),INTERVAL 1 DAY),DATE_ADD(DATE_SUB(DATE_ADD(@new_date, INTERVAL 2 year),INTERVAL 1 DAY), INTERVAL 40 DAY));
SELECT 'success' as returnmessage;
SET returnmsg='success';
  
SET finished = 0; -- hence reset this value for cursor.


  END IF;
   
  
  
END LOOP;

 close curTempTable_n; 

SELECT 'success' as returnmessage;
SET returnmsg='success';
  





End$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_test_wf_id` (IN `equipment_id` INT, IN `unit_id` INT, IN `test_id` INT, OUT `test_wf_id` VARCHAR(45))   begin
	-- Validation WF ID = VAL_WF_EQID_UNITID_YR_MONTH_UUID
    select concat('T-',equipment_id,'-',unit_id,'-',test_id,'-',UNIX_TIMESTAMP()) into test_wf_id;
End$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_val_wf_id` (IN `equipment_id` INT, IN `unit_id` INT, OUT `val_wf_id` VARCHAR(45))   begin
	-- Validation WF ID = VAL_WF_EQID_UNITID_YR_MONTH_UUID
    select concat('V-',equipment_id,'-',unit_id,'-',UNIX_TIMESTAMP()) into val_wf_id;
End$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_val_wf_id_with_frequency` (IN `equipment_id` INT, IN `unit_id` INT, IN `frequency` VARCHAR(3), OUT `val_wf_id` VARCHAR(45))   BEGIN
    DECLARE base_wf_id VARCHAR(45);
    DECLARE freq_suffix VARCHAR(5);
    DECLARE timestamp_val BIGINT;
    DECLARE existing_count INT;
    DECLARE retry_count INT DEFAULT 0;
    
    generate_unique_full_id:LOOP
        -- Generate UNIX timestamp for current datetime
        SET timestamp_val = UNIX_TIMESTAMP(NOW(6)) + retry_count;
        
        -- Create base workflow ID
        SET base_wf_id = CONCAT('V-', equipment_id, '-', unit_id, '-', timestamp_val);
        
        -- Add frequency suffix
        SET freq_suffix = CASE frequency
            WHEN '6M' THEN '-6M'
            WHEN 'Y' THEN '-Y'  
            WHEN '2Y' THEN '-2Y'
            ELSE CONCAT('-', frequency)
        END;
        
        -- Generate complete workflow ID
        SET val_wf_id = CONCAT(base_wf_id, freq_suffix);
        
        -- Check if this complete ID already exists
        SELECT COUNT(*) INTO existing_count 
        FROM (
            SELECT val_wf_id as id FROM tbl_proposed_val_schedules WHERE val_wf_id = val_wf_id
            UNION ALL
            SELECT val_wf_id as id FROM tbl_val_schedules WHERE val_wf_id = val_wf_id
        ) as existing_ids;
        
        -- If unique, exit loop
        IF existing_count = 0 THEN
            LEAVE generate_unique_full_id;
        END IF;
        
        -- Increment retry counter
        SET retry_count = retry_count + 1;
        
        -- Safety check to prevent infinite loop
        IF retry_count >= 100 THEN
            LEAVE generate_unique_full_id;
        END IF;
        
    END LOOP generate_unique_full_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `generate_val_wf_schedule_id` (IN `equipment_id` INT, IN `unit_id` INT, IN `schedule_year` INT, OUT `val_wf_schedule_id` VARCHAR(45))   begin
	-- Validation WF ID = VAL_WF_EQID_UNITID_YR_MONTH_UUID
    select concat('S-',equipment_id,'-',unit_id,'-',schedule_year,'-',UNIX_TIMESTAMP()) into val_wf_schedule_id;
End$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_pending_val_routinetests_count` (IN `p_equip_id` INT)   BEGIN
    DECLARE v_pending_validations INT;
    DECLARE v_pending_routine_tests INT;

    -- Count pending validations
    SELECT COUNT(*)
    INTO v_pending_validations
    FROM tbl_val_schedules t1
    INNER JOIN equipments t2 ON t1.equip_id = t2.equipment_id
    WHERE t1.val_wf_id NOT IN (SELECT val_wf_id FROM tbl_val_wf_tracking_details)
      AND t1.val_wf_status = 'Active'
      AND t1.equip_id = p_equip_id
      AND t2.equipment_status = 'Active';

    -- Count pending routine tests
    SELECT COUNT(*)
    INTO v_pending_routine_tests
    FROM tbl_routine_test_schedules t1
    INNER JOIN equipments t2 ON t1.equip_id = t2.equipment_id
    LEFT JOIN tbl_routine_test_wf_tracking_details t3 ON t1.routine_test_wf_id = t3.routine_test_wf_id
    WHERE t3.routine_test_wf_id IS NULL
      AND t1.routine_test_wf_status = 'Active'
      AND t1.equip_id = p_equip_id
      AND t2.equipment_status = 'Active';

    -- Return the results
    SELECT 
        p_equip_id AS equipment_id,
        v_pending_validations AS pending_validations,
        v_pending_routine_tests AS pending_routine_tests,
        (v_pending_validations + v_pending_routine_tests) AS total_pending_tests;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `InsertApprovalTracking` (IN `p_val_wf_id` VARCHAR(45), IN `p_iteration_start_datetime` DATETIME, IN `p_iteration_completion_status` VARCHAR(45), IN `p_iteration_status` VARCHAR(45), IN `p_engg_app_submission_date_time` DATETIME, IN `p_engg_app_sbmitted_by` VARCHAR(45))   BEGIN
    DECLARE next_iteration_id INT;
    
    -- Check if any records exist for this val_wf_id
    SELECT IFNULL(MAX(iteration_id) + 1, 1) INTO next_iteration_id
    FROM tbl_val_wf_approval_tracking_details
    WHERE val_wf_id = p_val_wf_id;
    
    -- Insert the new record with the calculated iteration_id
    INSERT INTO tbl_val_wf_approval_tracking_details (
        val_wf_id,
        iteration_id,
        iteration_start_datetime,
        iteration_completion_status,
        iteration_status,
        engg_app_submission_date_time,
        engg_app_sbmitted_by
        -- Add more columns as needed
    ) VALUES (
        p_val_wf_id,
        next_iteration_id,
        p_iteration_start_datetime,
        p_iteration_completion_status,
        p_iteration_status,
        p_engg_app_submission_date_time,
        p_engg_app_sbmitted_by
        -- Add more values as needed
    );
    
    -- Return the iteration_id that was used (optional)
    SELECT next_iteration_id AS used_iteration_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `kill_pending_routine_tests` (IN `equip_id` INT)   BEGIN
    DECLARE record_count INT;
    DECLARE processed_count INT DEFAULT 0;
    DECLARE current_routine_test_wf_id varchar(45);
    DECLARE current_unit_id INT;
    DECLARE current_planned_start_date DATETIME;
    DECLARE exit_flag BOOLEAN DEFAULT FALSE;
    DECLARE error_message TEXT;
    
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 error_message = MESSAGE_TEXT;
        SET exit_flag = TRUE;
        INSERT INTO error_log (error_message, equip_id, current_val_wf_id, current_unit_id, current_planned_start_date, operation_name)
        VALUES (error_message, equip_id, current_routine_test_wf_id, current_unit_id, current_planned_start_date, 'kill_pending_routine_tests');
        ROLLBACK;
    END;

    -- Start transaction
    START TRANSACTION;

    -- Create a temporary table to store the records to be processed
    CREATE TEMPORARY TABLE temp_records (
        routine_test_wf_id varchar(45),
        unit_id INT,
        routine_test_wf_planned_start_date DATETIME
    ) DEFAULT CHARSET='utf8mb4' COLLATE='utf8mb4_general_ci';

    -- Populate the temporary table with the records to be processed
    INSERT INTO temp_records (routine_test_wf_id, unit_id, routine_test_wf_planned_start_date)
    SELECT t1.routine_test_wf_id, t1.unit_id, t1.routine_test_wf_planned_start_date
    FROM tbl_routine_test_schedules t1
    INNER JOIN equipments t2 ON t1.equip_id = t2.equipment_id
    WHERE t1.routine_test_wf_id NOT IN (SELECT routine_test_wf_id FROM tbl_routine_test_wf_tracking_details)
      AND t1.routine_test_wf_status = 'Active'
      AND t1.equip_id = equip_id
      AND t2.equipment_status = 'Inactive'
    ORDER BY t1.routine_test_wf_planned_start_date;

    -- Get the count of records
    SELECT COUNT(*) INTO record_count FROM temp_records;

    -- Check if there are any records
    IF record_count = 0 THEN
        SELECT 'none' AS result;
    ELSE
        -- Process each record
        WHILE processed_count < record_count AND NOT exit_flag DO
            -- Get the next record to process
            SELECT routine_test_wf_id, unit_id, routine_test_wf_planned_start_date 
            INTO current_routine_test_wf_id, current_unit_id, current_planned_start_date
            FROM temp_records
            LIMIT 1 OFFSET processed_count;

            -- Execute the provided SQL code for each record
            BEGIN
                DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
                BEGIN
                    GET DIAGNOSTICS CONDITION 1 error_message = MESSAGE_TEXT;
                    SET exit_flag = TRUE;
                    INSERT INTO error_log (error_message, equip_id, current_val_wf_id, current_unit_id, current_planned_start_date, operation_name)
					VALUES (error_message, equip_id, current_routine_test_wf_id, current_unit_id, current_planned_start_date, 'start_routine_test_task');
        
                END;

                CALL `start_routine_test_task`(current_unit_id, equip_id, current_planned_start_date, current_routine_test_wf_id, 0);
            END;

            IF NOT exit_flag THEN
                UPDATE tbl_test_schedules_tracking 
                SET test_wf_current_stage = 99 
                WHERE val_wf_id = current_routine_test_wf_id;

                UPDATE tbl_routine_test_wf_tracking_details 
                SET routine_test_wf_current_stage = 99 
                WHERE routine_test_wf_id = current_routine_test_wf_id;

                SET processed_count = processed_count + 1;
            END IF;
        END WHILE;

        -- Check if an error occurred
        IF exit_flag THEN
            SELECT 'Error occurred. Transaction rolled back.' AS result, error_message AS error_details;
            SELECT current_unit_id, equip_id, current_planned_start_date, current_routine_test_wf_id, 0;
            ROLLBACK;
        ELSE
            COMMIT;
            -- Return the result
            SELECT CONCAT('killed_', processed_count) AS result;
        END IF;
    END IF;

    -- Clean up
    DROP TEMPORARY TABLE IF EXISTS temp_records;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `kill_pending_validations` (IN `equip_id` INT)   BEGIN
    DECLARE record_count INT;
    DECLARE processed_count INT DEFAULT 0;
    DECLARE current_val_wf_id VARCHAR(45);
    DECLARE current_unit_id INT;
    DECLARE current_planned_start_date DATETIME;
    DECLARE exit_flag BOOLEAN DEFAULT FALSE;
    DECLARE error_message TEXT;
    
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 error_message = MESSAGE_TEXT;
        SET exit_flag = TRUE;
        INSERT INTO error_log (error_message, equip_id, current_val_wf_id, current_unit_id, current_planned_start_date, operation_name)
        VALUES (error_message, equip_id, current_val_wf_id, current_unit_id, current_planned_start_date, 'kill_pending_validations');
        ROLLBACK;
    END;

    -- Start transaction
    START TRANSACTION;

    -- Create a temporary table to store the records to be processed
    CREATE TEMPORARY TABLE temp_records (
        val_wf_id VARCHAR(45),
        unit_id INT,
        val_wf_planned_start_date DATETIME
    ) DEFAULT CHARSET='utf8mb4' COLLATE='utf8mb4_general_ci';

    -- Populate the temporary table with the records to be processed
    INSERT INTO temp_records (val_wf_id, unit_id, val_wf_planned_start_date)
    SELECT t1.val_wf_id, t1.unit_id, t1.val_wf_planned_start_date
    FROM tbl_val_schedules t1
    INNER JOIN equipments t2 ON t1.equip_id = t2.equipment_id
    WHERE t1.val_wf_id NOT IN (SELECT val_wf_id FROM tbl_val_wf_tracking_details)
      AND t1.val_wf_status = 'Active'
      AND t1.equip_id = equip_id
      AND t2.equipment_status = 'Inactive'
    ORDER BY t1.val_wf_planned_start_date;

    -- Get the count of records
    SELECT COUNT(*) INTO record_count FROM temp_records;

    -- Check if there are any records
    IF record_count = 0 THEN
        SELECT 'none' AS result;
    ELSE
        -- Process each record
        WHILE processed_count < record_count AND NOT exit_flag DO
            -- Get the next record to process
            SELECT val_wf_id, unit_id, val_wf_planned_start_date 
            INTO current_val_wf_id, current_unit_id, current_planned_start_date
            FROM temp_records
            LIMIT 1 OFFSET processed_count;

            -- Execute the provided SQL code for each record
            BEGIN
                DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
                BEGIN
                    GET DIAGNOSTICS CONDITION 1 error_message = MESSAGE_TEXT;
                    SET exit_flag = TRUE;
                    INSERT INTO error_log (error_message, equip_id, current_val_wf_id, current_unit_id, current_planned_start_date, operation_name)
                    VALUES (error_message, equip_id, current_val_wf_id, current_unit_id, current_planned_start_date, 'start_validation_task');
                END;

                CALL `start_validation_task`(current_unit_id, equip_id, current_planned_start_date, current_val_wf_id, 0);
            END;

            IF NOT exit_flag THEN
                UPDATE tbl_test_schedules_tracking 
                SET test_wf_current_stage = 99 
                WHERE val_wf_id = current_val_wf_id;

                UPDATE tbl_val_wf_tracking_details 
                SET val_wf_current_stage = 99 
                WHERE val_wf_id = current_val_wf_id;

                SET processed_count = processed_count + 1;
            END IF;
        END WHILE;

        -- Check if an error occurred
        IF exit_flag THEN
            SELECT 'Error occurred. Transaction rolled back.' AS result, error_message AS error_details;
            SELECT current_unit_id, equip_id, current_planned_start_date, current_val_wf_id, 0;
            ROLLBACK;
        ELSE
            COMMIT;
            -- Return the result
            SELECT CONCAT('killed_', processed_count) AS result;
        END IF;
    END IF;

    -- Clean up
    DROP TEMPORARY TABLE IF EXISTS temp_records;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_enhanced_routine_date` (IN `p_test_conducted_date` DATE, IN `p_frequency` VARCHAR(5), IN `p_original_planned_date` DATE, OUT `p_next_routine_date` DATE)   proc_main: BEGIN
    DECLARE v_adjustment_reason VARCHAR(255);
    
    -- Validate frequency (Q, H, Y, 2Y only)
    IF NOT fn_validate_frequency(p_frequency) THEN
        SET p_next_routine_date = NULL;
        INSERT INTO auto_schedule_log (
            trigger_type, original_id, frequency, status, error_details, business_rule_applied
        ) VALUES (
            'frequency_validation_error', 'FREQ_CHECK', p_frequency, 'error',
            CONCAT('Invalid frequency: ', p_frequency, '. Supported: Q, H, Y, 2Y'),
            'frequency_validation'
        );
        LEAVE proc_main;
    END IF;
    
    -- Use simplified date calculation
    CALL sp_calculate_simple_date(
        p_test_conducted_date, p_frequency, p_next_routine_date, v_adjustment_reason
    );
    
    -- Log enhanced routine calculation
    INSERT INTO auto_schedule_log (
        trigger_type, original_id, frequency, original_execution_date, calculated_date,
        status, business_rule_applied, notes
    ) VALUES (
        'enhanced_routine_calculation', 'ENHANCED_ROUTINE', p_frequency, p_test_conducted_date, p_next_routine_date,
        'success', 'enhanced_routine_date_calculation',
        CONCAT('Enhanced calculation for frequency ', p_frequency, ': ', IFNULL(v_adjustment_reason, 'Standard'))
    );
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_simple_date` (IN `p_base_date` DATE, IN `p_frequency` VARCHAR(5), OUT `p_calculated_date` DATE, OUT `p_adjustment_reason` VARCHAR(255))   proc_main: BEGIN
    DECLARE v_calculated_date DATE;
    
    SET p_adjustment_reason = '';
    
    -- Basic frequency-based calculation (Q, H, Y, 2Y)
    IF p_frequency = 'Q' THEN
        SET v_calculated_date = DATE_SUB(DATE_ADD(p_base_date, INTERVAL 3 MONTH), INTERVAL 1 DAY);
    ELSEIF p_frequency = 'H' THEN
        SET v_calculated_date = DATE_SUB(DATE_ADD(p_base_date, INTERVAL 6 MONTH), INTERVAL 1 DAY);
    ELSEIF p_frequency = 'Y' THEN
        SET v_calculated_date = DATE_SUB(DATE_ADD(p_base_date, INTERVAL 1 YEAR), INTERVAL 1 DAY);
    ELSEIF p_frequency = '2Y' THEN
        SET v_calculated_date = DATE_SUB(DATE_ADD(p_base_date, INTERVAL 2 YEAR), INTERVAL 1 DAY);
    ELSE
        SET p_calculated_date = NULL;
        SET p_adjustment_reason = CONCAT('Unsupported frequency: ', p_frequency);
        LEAVE proc_main;
    END IF;
    
    -- Essential Feature: Leap Year Handling
    IF MONTH(v_calculated_date) = 2 AND DAY(v_calculated_date) = 29 THEN
        -- If calculated date is Feb 29 but target year is not leap year
        IF (YEAR(v_calculated_date) % 4 != 0) OR 
           (YEAR(v_calculated_date) % 100 = 0 AND YEAR(v_calculated_date) % 400 != 0) THEN
            SET v_calculated_date = DATE_SUB(v_calculated_date, INTERVAL 1 DAY); -- Feb 28
            SET p_adjustment_reason = 'Leap year adjustment: moved to Feb 28';
        END IF;
    END IF;
    
    -- REMOVED: Year boundary validation (no longer limiting to 3 years)
    -- System now allows unlimited future scheduling
    
    SET p_calculated_date = v_calculated_date;
    
    -- Log the simplified calculation
    INSERT INTO auto_schedule_log (
        trigger_type, original_id, frequency, original_execution_date, calculated_date,
        status, business_rule_applied, notes
    ) VALUES (
        'simplified_date_calculation', 'UNLIMITED_CALC', p_frequency, p_base_date, p_calculated_date,
        'success', 'unlimited_date_calculation',
        CONCAT('Simplified calculation (no year limits): ', IFNULL(p_adjustment_reason, 'Standard calculation'))
    );
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_clean_emailreminder_logs` (IN `p_job_log_retention_days` INT, IN `p_email_log_retention_days` INT)   BEGIN
    DECLARE deleted_job_logs INT DEFAULT 0;
    DECLARE deleted_email_logs INT DEFAULT 0;
    DECLARE deleted_system_logs INT DEFAULT 0;
    
    -- Set default values if parameters are NULL
    IF p_job_log_retention_days IS NULL THEN
        SET p_job_log_retention_days = 90;
    END IF;
    
    IF p_email_log_retention_days IS NULL THEN
        SET p_email_log_retention_days = 365;
    END IF;
    
    -- Delete old job logs
    DELETE FROM `tbl_email_reminder_job_logs` 
    WHERE execution_start_time < DATE_SUB(NOW(), INTERVAL p_job_log_retention_days DAY);
    SET deleted_job_logs = ROW_COUNT();
    
    -- Delete old email logs (recipients will be deleted by cascade)
    DELETE FROM `tbl_email_reminder_logs` 
    WHERE sent_datetime < DATE_SUB(NOW(), INTERVAL p_email_log_retention_days DAY);
    SET deleted_email_logs = ROW_COUNT();
    
    -- Delete old system logs
    DELETE FROM `tbl_email_reminder_system_logs` 
    WHERE log_datetime < DATE_SUB(NOW(), INTERVAL p_job_log_retention_days DAY);
    SET deleted_system_logs = ROW_COUNT();
    
    -- Return cleanup results
    SELECT deleted_job_logs, deleted_email_logs, deleted_system_logs;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_get_emailreminder_health_status` (IN `p_hours` INT)   BEGIN
    -- Set default value if parameter is NULL
    IF p_hours IS NULL THEN
        SET p_hours = 24;
    END IF;
    
    SELECT 
        COUNT(*) as total_jobs,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_jobs,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_jobs,
        COUNT(CASE WHEN status = 'skipped' THEN 1 END) as skipped_jobs,
        COUNT(CASE WHEN execution_time_seconds > 300 THEN 1 END) as slow_jobs,
        AVG(execution_time_seconds) as avg_execution_time,
        SUM(emails_sent) as total_emails_sent,
        SUM(emails_failed) as total_emails_failed
    FROM `tbl_email_reminder_job_logs` 
    WHERE execution_start_time >= DATE_SUB(NOW(), INTERVAL p_hours HOUR);
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_handle_routine_id_collision` (IN `p_base_id` VARCHAR(50), IN `p_base_frequency` VARCHAR(5), OUT `p_final_id` VARCHAR(50))   BEGIN
    DECLARE v_attempt_count INT DEFAULT 0;
    DECLARE v_max_attempts INT DEFAULT 10;
    DECLARE v_current_id VARCHAR(50);
    DECLARE v_suffix VARCHAR(10);
    DECLARE v_id_prefix VARCHAR(45);
    
    SET v_current_id = p_base_id;
    SET p_final_id = p_base_id;
    
    -- Extract ID prefix (everything before the last dash)
    SET v_id_prefix = SUBSTRING_INDEX(p_base_id, '-', LENGTH(p_base_id) - LENGTH(REPLACE(p_base_id, '-', '')) + 1 - 1);
    
    -- Handle collisions with frequency-based suffix progression
    WHILE EXISTS (SELECT 1 FROM tbl_routine_test_schedules WHERE routine_test_wf_id = v_current_id) 
          AND v_attempt_count < v_max_attempts DO
        
        SET v_attempt_count = v_attempt_count + 1;
        
        -- Create suffix: A, AA, AAA, AAAA, etc.
        SET v_suffix = CONCAT(p_base_frequency, REPEAT('A', v_attempt_count));
        
        -- Reconstruct ID with new suffix
        SET v_current_id = CONCAT(v_id_prefix, '-', v_suffix);
        
    END WHILE;
    
    -- If still collision after max attempts, add numeric suffix
    IF EXISTS (SELECT 1 FROM tbl_routine_test_schedules WHERE routine_test_wf_id = v_current_id) THEN
        SET v_current_id = CONCAT(v_id_prefix, '-', p_base_frequency, 'A', UNIX_TIMESTAMP());
    END IF;
    
    SET p_final_id = v_current_id;
    
    -- Log collision handling
    IF v_attempt_count > 0 THEN
        INSERT INTO auto_schedule_log (
            trigger_type, original_id, new_id, action_taken, status, business_rule_applied, notes
        ) VALUES (
            'routine_id_collision_handled', p_base_id, p_final_id, 'Resolved routine test ID collision',
            'success', 'enhanced_routine_id_collision_handling',
            CONCAT('Collision attempts: ', v_attempt_count, ', Final suffix: ', SUBSTRING_INDEX(p_final_id, '-', -1))
        );
    END IF;
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_handle_validation_id_collision` (IN `p_base_id` VARCHAR(50), IN `p_base_frequency` VARCHAR(5), OUT `p_final_id` VARCHAR(50))   BEGIN
    DECLARE v_attempt_count INT DEFAULT 0;
    DECLARE v_max_attempts INT DEFAULT 10;
    DECLARE v_current_id VARCHAR(50);
    DECLARE v_suffix VARCHAR(10);
    DECLARE v_id_prefix VARCHAR(45);
    
    SET v_current_id = p_base_id;
    SET p_final_id = p_base_id;
    
    -- Extract ID prefix (everything before the last dash)
    SET v_id_prefix = SUBSTRING_INDEX(p_base_id, '-', LENGTH(p_base_id) - LENGTH(REPLACE(p_base_id, '-', '')) + 1 - 1);
    
    -- Handle collisions with frequency-based suffix progression
    WHILE EXISTS (SELECT 1 FROM tbl_val_schedules WHERE val_wf_id = v_current_id) 
          AND v_attempt_count < v_max_attempts DO
        
        SET v_attempt_count = v_attempt_count + 1;
        
        -- Create suffix: A, AA, AAA, AAAA, etc.
        SET v_suffix = CONCAT(p_base_frequency, REPEAT('A', v_attempt_count));
        
        -- Reconstruct ID with new suffix
        SET v_current_id = CONCAT(v_id_prefix, '-', v_suffix);
        
    END WHILE;
    
    -- If still collision after max attempts, add numeric suffix
    IF EXISTS (SELECT 1 FROM tbl_val_schedules WHERE val_wf_id = v_current_id) THEN
        SET v_current_id = CONCAT(v_id_prefix, '-', p_base_frequency, 'A', UNIX_TIMESTAMP());
    END IF;
    
    SET p_final_id = v_current_id;
    
    -- Log collision handling
    IF v_attempt_count > 0 THEN
        INSERT INTO auto_schedule_log (
            trigger_type, original_id, new_id, action_taken, status, business_rule_applied, notes
        ) VALUES (
            'id_collision_handled', p_base_id, p_final_id, 'Resolved ID collision',
            'success', 'enhanced_id_collision_handling',
            CONCAT('Collision attempts: ', v_attempt_count, ', Final suffix: ', SUBSTRING_INDEX(p_final_id, '-', -1))
        );
    END IF;
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `start_routine_test_task` (IN `unit_id` INT, IN `equip_id` INT, IN `planned_start_date` DATE, IN `routine_test_wfid` VARCHAR(45), IN `ini_by_user_id` INT)   begin

DECLARE finished INTEGER DEFAULT 0;
Declare testID int;
declare vendorID int;
Declare rtTestID int;
declare _routine_test_req_id int;

select test_id,routine_test_req_id into rtTestID,_routine_test_req_id from tbl_routine_test_schedules where routine_test_wf_id=routine_test_wfid;

select distinct t1.test_id,vendor_id into testID, vendorID from equipment_test_vendor_mapping t1, tbl_routine_test_schedules t2 where t1.test_id=t2.test_id and t1.equipment_id =t2.equip_id 
and t2.test_id=rtTestID and t2.equip_id=equip_id
and mapping_status='Active';



set time_zone = '+05:30';


insert into tbl_routine_test_wf_tracking_details (routine_test_wf_id, equipment_id,unit_id,actual_wf_start_datetime,wf_initiated_by_user_id,routine_test_wf_current_stage,stage_assigned_datetime)
values(routine_test_wfid,equip_id,unit_id,current_timestamp(),ini_by_user_id,1,current_timestamp());

insert into audit_trail  (val_wf_id,test_wf_id,user_id,time_stamp,wf_stage)
values (routine_test_wfid,'',ini_by_user_id,current_timestamp(),'1');

 call generate_test_wf_id(equip_id,unit_id,testID,@test_wf_id);
    

   insert into tbl_test_schedules_tracking (unit_id, equip_id,test_id,vendor_id,test_wf_id,val_wf_id,test_wf_current_stage,stage_assigned_datetime,test_type,routine_test_request_id)
    values (unit_id,equip_id,testID,vendorID,@test_wf_id,routine_test_wfid,1,current_timestamp(),'R',_routine_test_req_id);  
       
  -- insert into tbl_test_schedules_tracking (unit_id, equip_id,test_id,vendor_id,test_wf_id,val_wf_id,test_wf_current_stage,stage_assigned_datetime)
   -- values (unit_id,equip_id,testID,vendorID,@test_wf_id,val_wf_id,1,current_timestamp());  
    
    
insert into audit_trail  (val_wf_id,test_wf_id,user_id,time_stamp,wf_stage)
values (routine_test_wfid,@test_wf_id,ini_by_user_id,current_timestamp(),'1');
    

end$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `start_validation_task` (IN `unit_id` INT, IN `equip_id` INT, IN `planned_start_date` DATE, IN `val_wf_id` VARCHAR(45), IN `ini_by_user_id` INT)   begin

DECLARE finished INTEGER DEFAULT 0;
Declare testID int;
declare vendorID int;




	-- declare cursor for employee email
	DECLARE curTests 
		CURSOR FOR 
			select test_id, vendor_id from equipment_test_vendor_mapping where equipment_id=equip_id and mapping_status='Active';

	-- declare NOT FOUND handler
	DECLARE CONTINUE HANDLER 
        FOR NOT FOUND SET finished = 1;


set time_zone = '+05:30';


insert into tbl_val_wf_tracking_details (val_wf_id, equipment_id,unit_id,actual_wf_start_datetime,wf_initiated_by_user_id,val_wf_current_stage,stage_assigned_datetime)
values(val_wf_id,equip_id,unit_id,current_timestamp(),ini_by_user_id,1,current_timestamp());

insert into audit_trail  (val_wf_id,test_wf_id,user_id,time_stamp,wf_stage)
values (val_wf_id,'',ini_by_user_id,current_timestamp(),'1');


	OPEN curTests;
	
    getTest:LOOP
    FETCH curTests INTO testID,vendorID;
		IF finished = 1 THEN 
			LEAVE getTest;
		END IF;
   
   call generate_test_wf_id(equip_id,unit_id,testID,@test_wf_id);
    

   insert into tbl_test_schedules_tracking (unit_id, equip_id,test_id,vendor_id,test_wf_id,val_wf_id,test_wf_current_stage,stage_assigned_datetime,test_type)
    values (unit_id,equip_id,testID,vendorID,@test_wf_id,val_wf_id,1,current_timestamp(),'V');  
       
  -- insert into tbl_test_schedules_tracking (unit_id, equip_id,test_id,vendor_id,test_wf_id,val_wf_id,test_wf_current_stage,stage_assigned_datetime)
   -- values (unit_id,equip_id,testID,vendorID,@test_wf_id,val_wf_id,1,current_timestamp());  
    
    
insert into audit_trail  (val_wf_id,test_wf_id,user_id,time_stamp,wf_stage)
values (val_wf_id,@test_wf_id,ini_by_user_id,current_timestamp(),'1');
    
    END LOOP getTest;
    CLOSE curTests;
end$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `USP_ADDVALREQADHOC` (IN `_unit_id` INT, IN `_equipment_id` INT, IN `_start_date` DATE, IN `_user_id` INT)   BEGIN
    DECLARE val_wf_id VARCHAR(255);

    -- Error handling
    DECLARE continue_handler INT DEFAULT 1;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    DECLARE EXIT HANDLER FOR SQLWARNING
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    -- Start transaction
    START TRANSACTION;

    -- Insert into tbl_val_schedules
    INSERT INTO tbl_val_schedules
        (unit_id, equip_id, val_wf_id, val_wf_planned_start_date, val_wf_status, 
        created_date_time, last_modified_date_time, is_adhoc, requested_by_user_id)
    VALUES (
        _unit_id,
        _equipment_id,
        CONCAT('V-', _equipment_id, '-', _unit_id, '-', UNIX_TIMESTAMP(UTC_TIMESTAMP()), '-A'),
        _start_date,
        'Active',
        UTC_TIMESTAMP(),
        UTC_TIMESTAMP(),
        'Y',
        _user_id
    );

    -- Get the generated val_wf_id
    SET val_wf_id = LAST_INSERT_ID();

    -- Commit the transaction
    COMMIT;

    -- Return the generated val_wf_id if needed
    SELECT val_wf_id AS generated_val_wf_id;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `USP_CREATERTSCHEDULES` (IN `_unit_id` INT, IN `_schedule_year` INT)   BEGIN

/*  DECLARING VARIABLES  */
DECLARE _EQUIPMENT_ID INT; 
DECLARE _LastTestConductedDate DATETIME;
DECLARE _FREQUENCYINMONTHS INT;
DECLARE _test_frequency char;
DECLARE _TEST_ID INT;
DECLARE _SCHEDULE_ID INT;
DECLARE _ROUTINE_REQUEST_ID INT;

insert into tbl_routine_test_wf_schedule_requests (schedule_year,unit_id) values (_schedule_year,_unit_id);
SELECT LAST_INSERT_ID() into _SCHEDULE_ID;

/*  FETCH Routine Test Requests & THEIR ELIGIBILITY IN A TEMP TABLE  */ 
CREATE TEMPORARY TABLE _RoutineTestRequest
select A.routine_test_request_id,A.unit_id,A.equipment_id,C.equipment_code,C.equipment_category,A.test_frequency,A.test_planned_start_date,A.test_id,B.test_wf_id,B.test_wf_current_stage,IF(IFNULL(B.test_wf_current_stage,0) IN (5,0) ,1,0) As 'ELIGIBILITY',
0 AS 'SCH_GENERATED',B.test_conducted_date As 'LastTestConductedDate'
,IF(A.test_frequency='Q',3, IF(A.test_frequency='H',6, IF(A.test_frequency='Y',12,
IF(A.test_frequency='2Y',24,0)))) AS 'FREQUENCYINMONTHS'
from tbl_routine_tests_requests A
left join tbl_test_schedules_tracking B on A.routine_test_request_id=B.routine_test_request_id  
left join equipments C on A.equipment_id=C.equipment_id
WHERE C.equipment_status = 'ACTIVE' and A.routine_test_status=1 and A.unit_id=_unit_id;


/*  UPDATE LASTTESTCONDUCTEDDATE FOR NEW EQUIPMENTS ADDED HAVING NO PRIMARY TESTS CONDUCTED YET*/ 
UPDATE _RoutineTestRequest SET LastTestConductedDate = CONVERT(test_planned_start_date, DATETIME) 
WHERE ELIGIBILITY =1 AND LastTestConductedDate IS NULL;




/*  CREATE TEMP TABLE FOR Routine Tests TO BE GENERATED  */
CREATE TEMPORARY TABLE _Routine_Test_Schedules
( schedule_id int,unit_id INT, equip_id INT, test_id INT, test_freq char,routine_wf_planned_start_date DATETIME, routine_wf_planned_end_date DATETIME,routine_test_req_id int);



/*  RUN A LOOP OVER _RoutineTestRequest TEMP TABLE FOR EACH ELIGIBLE EQUIPMENT HAVING SCHEDULE NOT YET GENERATED  */
WHILE (SELECT COUNT(EQUIPMENT_ID)<>0 FROM _RoutineTestRequest WHERE ELIGIBILITY =1 AND SCH_GENERATED=0)  
DO

		/*  FETCH DATE FOR THE TOP 1 RECORD IN VARIABLES  */
		SELECT EQUIPMENT_ID, LastTestConductedDate, FREQUENCYINMONTHS, test_frequency,test_id,routine_test_request_id INTO 
        _EQUIPMENT_ID, _LastTestConductedDate, _FREQUENCYINMONTHS, _test_frequency,_TEST_ID,_ROUTINE_REQUEST_ID FROM _RoutineTestRequest 
        WHERE ELIGIBILITY =1 AND SCH_GENERATED=0 ORDER BY EQUIPMENT_ID, LastTestConductedDate desc LIMIT 1;     
        -- Added LastTestConductedDate desc in the Orderby clause 26dec23
   

		/*  RUN A LOOP UNTILL ADDITION OF INTERVAL DOESNT EXCEEDS CURRENT YEAR  */		
		/*	WHILE (YEAR( DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH) ) = _schedule_year) */
    WHILE (YEAR( calculate_enddate_months(_LastTestConductedDate,_FREQUENCYINMONTHS) ) = _schedule_year) 
        DO	
		
			/*  CREATE VALIDATION INTO TEMP TABLE OF VALIADATIONS  */		
			INSERT INTO _Routine_Test_Schedules VALUES
            (_SCHEDULE_ID,_UNIT_ID,_EQUIPMENT_ID,_TEST_ID, _test_frequency,DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH), NULL,_ROUTINE_REQUEST_ID);
            
           
            
            /*  INSERT INTO VALIDATIONS MAIN TABLE 	*/
           INSERT INTO tbl_proposed_routine_test_schedules
			(schedule_id,unit_id, equip_id, test_id, routine_test_wf_id, routine_test_wf_planned_start_date, routine_test_wf_planned_end_date,
			routine_test_wf_status, created_date_time, last_modified_date_time,routine_test_req_id)
			VALUES
			( _SCHEDULE_ID,_unit_id, _EQUIPMENT_ID, _TEST_ID,
			concat('R-',_EQUIPMENT_ID,'-',_unit_id,'-', _TEST_ID,'-',
			UNIX_TIMESTAMP(DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH)),'-',
			TRIM(_test_frequency)),
			/*DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH),*/
            calculate_enddate_months(_LastTestConductedDate,_FREQUENCYINMONTHS), 
			NULL, 'ACTIVE', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP(),_ROUTINE_REQUEST_ID); 	
			
            /*  INCREMENT INTERVAL  */            
            /*   SET _LastTestConductedDate = DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH); */
              SET _LastTestConductedDate = calculate_enddate_months(_LastTestConductedDate,_FREQUENCYINMONTHS); 
            
		END WHILE;

		/*  UPDATE SCHEDULE GENERATED FLAG IN TEMP TABLE OF EQUIPMENTS  */        
        UPDATE _RoutineTestRequest SET SCH_GENERATED = 1 WHERE EQUIPMENT_ID=_EQUIPMENT_ID and TEST_ID=_TEST_ID;
        
END WHILE;

/*  SELECT EQUIPMENTS ANALYSIED & INSERTED VALIDATIONS IN SYSTEM  */
 -- SELECT * FROM _RoutineTestRequest; 
-- SELECT * FROM _Routine_Test_Schedules;

/*  DROP TEMP TABLES  */
DROP TEMPORARY TABLE _RoutineTestRequest;
DROP TEMPORARY TABLE _Routine_Test_Schedules;



END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `USP_CREATESCHEDULES` (IN `_unit_id` INT, IN `_schedule_year` INT)   BEGIN

/*  DECLARING VARIABLES  */
DECLARE _EQUIPMENT_ID INT; DECLARE _LastTestConductedDate DATETIME;
DECLARE _FREQUENCYINMONTHS INT;DECLARE _validation_frequency CHAR(2);
DECLARE _SCHEDULE_ID INT;
insert into tbl_val_wf_schedule_requests (schedule_year,unit_id) values (_schedule_year,_unit_id);
SELECT LAST_INSERT_ID() into _SCHEDULE_ID;
/*  FETCH EQUIPMENTS & THEIR ELIGIBILITY IN A TEMP TABLE  */ 
CREATE TEMPORARY TABLE _Equipments
SELECT A.equipment_id,A.equipment_code,A.unit_id,A.department_id,A.equipment_category,A.validation_frequency,
IF(A.validation_frequency='Q',3, IF(A.validation_frequency='H',6, IF(A.validation_frequency='Y',12,
IF(A.validation_frequency='2Y',24,0)))) AS 'FREQUENCYINMONTHS',
A.equipment_status,A.equipment_last_modification_datetime,A.equipment_addition_date, 
B.LastTestConductedDate, B.test_wf_current_stage, 
IF(IFNULL(B.test_wf_current_stage,0) IN (5,0) ,1,0) As 'ELIGIBILITY', 0 AS 'SCH_GENERATED' FROM equipments A
LEFT JOIN (Select *, MAX(test_conducted_date) As 'LastTestConductedDate' FROM tbl_test_schedules_tracking 
WHERE test_ID IN (SELECT primary_test_id FROM units where unit_id = _unit_id) and test_type='V'
GROUP BY equip_id,test_id) B ON A.equipment_ID = B.equip_ID 
LEFT JOIN units C ON A.unit_id = C.unit_id
-- WHERE equipment_status = 'ACTIVE';
WHERE equipment_status = 'ACTIVE' and A.unit_id = _unit_id;

/*  UPDATE LASTTESTCONDUCTEDDATE FOR NEW EQUIPMENTS ADDED HAVING NO PRIMARY TESTS CONDUCTED YET*/ 
UPDATE _Equipments SET LastTestConductedDate = CONVERT(equipment_addition_date, DATETIME) 
WHERE ELIGIBILITY =1 AND LastTestConductedDate IS NULL;

/*  CREATE TEMP TABLE FOR VALIDATIONS TO BE GENERATED  */
CREATE TEMPORARY TABLE _Equipments_Schedules
( schedule_id int,unit_id INT, equip_id INT, val_wf_planned_start_date DATETIME, val_wf_planned_end_date DATETIME);

/*  RUN A LOOP OVER EQUIPMENTS TEMP TABLE FOR EACH ELIGIBLE EQUIPMENT HAVING SCHEDULE NOT YET GENERATED  */
WHILE (SELECT COUNT(EQUIPMENT_ID)<>0 FROM _Equipments WHERE ELIGIBILITY =1 AND SCH_GENERATED=0)  
DO

		/*  FETCH DATE FOR THE TOP 1 RECORD IN VARIABLES  */
		SELECT EQUIPMENT_ID, LastTestConductedDate, FREQUENCYINMONTHS, validation_frequency INTO 
        _EQUIPMENT_ID, _LastTestConductedDate, _FREQUENCYINMONTHS, _validation_frequency FROM _Equipments 
        WHERE ELIGIBILITY =1 AND SCH_GENERATED=0 ORDER BY EQUIPMENT_ID LIMIT 1;         

		/*  RUN A LOOP UNTILL ADDITION OF INTERVAL DOESNT EXCEEDS CURRENT YEAR  */		
		-- WHILE (YEAR( DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH) ) = _schedule_year)
        WHILE (YEAR( DATE_ADD(DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH), INTERVAL -1 DAY) ) = _schedule_year)
        DO	
		
			/*  CREATE VALIDATION INTO TEMP TABLE OF VALIADATIONS  */		
			INSERT INTO _Equipments_Schedules VALUES
            -- (_SCHEDULE_ID,_UNIT_ID,_EQUIPMENT_ID, DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH), NULL);
            (_SCHEDULE_ID,_UNIT_ID,_EQUIPMENT_ID, DATE_ADD(DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH), INTERVAL -1 DAY), NULL);
            
            /*  INSERT INTO VALIDATIONS MAIN TABLE  */		
            INSERT INTO tbl_proposed_val_schedules
			(schedule_id,unit_id, equip_id, val_wf_id, val_wf_planned_start_date, val_wf_planned_end_date,
			val_wf_status, created_date_time, last_modified_date_time)
			VALUES
			/*( _SCHEDULE_ID,_unit_id, _EQUIPMENT_ID, 
			concat('V-',_EQUIPMENT_ID,'-',_unit_id,'-', 
			UNIX_TIMESTAMP(DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH)),'-',
			TRIM(_validation_frequency)),
			DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH),
			NULL, 'ACTIVE', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP());*/
            
            ( _SCHEDULE_ID,_unit_id, _EQUIPMENT_ID, 
			concat('V-',_EQUIPMENT_ID,'-',_unit_id,'-', 
			UNIX_TIMESTAMP(DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH)),'-',
			TRIM(_validation_frequency)),
			DATE_ADD(DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH), INTERVAL -1 DAY),
			NULL, 'ACTIVE', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP());
			
            /*  INCREMENT INTERVAL  */            
            SET _LastTestConductedDate = DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH);
            
		END WHILE;

		/*  UPDATE SCHEDULE GENERATED FLAG IN TEMP TABLE OF EQUIPMENTS  */        
        UPDATE _Equipments SET SCH_GENERATED = 1 WHERE EQUIPMENT_ID=_EQUIPMENT_ID;
        
END WHILE;

/*  SELECT EQUIPMENTS ANALYSIED & INSERTED VALIDATIONS IN SYSTEM  */
-- SELECT * FROM _Equipments; 
-- SELECT * FROM _Equipments_Schedules;

/*  DROP TEMP TABLES  */
-- DROP TEMPORARY TABLE _Equipments;
-- DROP TEMPORARY TABLE _Equipments_Schedules;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `USP_DYNAMIC_CREATESCHEDULES` (IN `_unit_id` INT, IN `_schedule_year` INT)   BEGIN

-- Store original SQL mode
    DECLARE original_sql_mode VARCHAR(500);
   

/*  DECLARING VARIABLES  */
DECLARE _EQUIPMENT_ID INT; DECLARE _LastTestConductedDate DATETIME;
DECLARE _FREQUENCYINMONTHS INT;DECLARE _validation_frequency CHAR(2);
DECLARE _SCHEDULE_ID INT;

 SET original_sql_mode = @@SESSION.sql_mode;
 -- Disable ONLY_FULL_GROUP_BY
    SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''));
    

insert into tbl_val_wf_schedule_requests (schedule_year,unit_id) values (_schedule_year,_unit_id);
SELECT LAST_INSERT_ID() into _SCHEDULE_ID;
/*  FETCH EQUIPMENTS & THEIR ELIGIBILITY IN A TEMP TABLE  */ 
CREATE TEMPORARY TABLE _Equipments
SELECT A.equipment_id,A.equipment_code,A.unit_id,A.department_id,A.equipment_category,A.validation_frequency,
IF(A.validation_frequency='Q',3, IF(A.validation_frequency='H',6, IF(A.validation_frequency='Y',12,
IF(A.validation_frequency='2Y',24,0)))) AS 'FREQUENCYINMONTHS',
A.equipment_status,A.equipment_last_modification_datetime,A.equipment_addition_date, 
B.LastTestConductedDate, B.test_wf_current_stage, 
IF(IFNULL(B.test_wf_current_stage,0) IN (5,0) ,1,0) As 'ELIGIBILITY', 0 AS 'SCH_GENERATED' FROM equipments A
LEFT JOIN (Select *, MAX(test_conducted_date) As 'LastTestConductedDate' FROM tbl_test_schedules_tracking 
WHERE test_ID IN (SELECT primary_test_id FROM units where unit_id = _unit_id) and test_type='V'
GROUP BY equip_id,test_id) B ON A.equipment_ID = B.equip_ID 
LEFT JOIN units C ON A.unit_id = C.unit_id
-- WHERE equipment_status = 'ACTIVE';
WHERE equipment_status = 'ACTIVE' and A.unit_id = _unit_id;

/*  UPDATE LASTTESTCONDUCTEDDATE FOR NEW EQUIPMENTS ADDED HAVING NO PRIMARY TESTS CONDUCTED YET*/ 
UPDATE _Equipments SET LastTestConductedDate = CONVERT(equipment_addition_date, DATETIME) 
WHERE ELIGIBILITY =1 AND LastTestConductedDate IS NULL;

/*  CREATE TEMP TABLE FOR VALIDATIONS TO BE GENERATED  */
CREATE TEMPORARY TABLE _Equipments_Schedules
( schedule_id int,unit_id INT, equip_id INT, val_wf_planned_start_date DATETIME, val_wf_planned_end_date DATETIME);

/*  RUN A LOOP OVER EQUIPMENTS TEMP TABLE FOR EACH ELIGIBLE EQUIPMENT HAVING SCHEDULE NOT YET GENERATED  */
WHILE (SELECT COUNT(EQUIPMENT_ID)<>0 FROM _Equipments WHERE ELIGIBILITY =1 AND SCH_GENERATED=0)  
DO

		/*  FETCH DATE FOR THE TOP 1 RECORD IN VARIABLES  */
		SELECT EQUIPMENT_ID, LastTestConductedDate, FREQUENCYINMONTHS, validation_frequency INTO 
        _EQUIPMENT_ID, _LastTestConductedDate, _FREQUENCYINMONTHS, _validation_frequency FROM _Equipments 
        WHERE ELIGIBILITY =1 AND SCH_GENERATED=0 ORDER BY EQUIPMENT_ID LIMIT 1;         

		/*  RUN A LOOP UNTILL ADDITION OF INTERVAL DOESNT EXCEEDS CURRENT YEAR  */		
		-- WHILE (YEAR( DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH) ) = _schedule_year)
        WHILE (YEAR( DATE_ADD(DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH), INTERVAL -1 DAY) ) = _schedule_year)
        DO	
		
			/*  CREATE VALIDATION INTO TEMP TABLE OF VALIADATIONS  */		
			INSERT INTO _Equipments_Schedules VALUES
            -- (_SCHEDULE_ID,_UNIT_ID,_EQUIPMENT_ID, DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH), NULL);
            (_SCHEDULE_ID,_UNIT_ID,_EQUIPMENT_ID, DATE_ADD(DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH), INTERVAL -1 DAY), NULL);
            
            /*  INSERT INTO VALIDATIONS MAIN TABLE  */		
            INSERT INTO tbl_proposed_val_schedules
			(schedule_id,unit_id, equip_id, val_wf_id, val_wf_planned_start_date, val_wf_planned_end_date,
			val_wf_status, created_date_time, last_modified_date_time)
			VALUES
			/*( _SCHEDULE_ID,_unit_id, _EQUIPMENT_ID, 
			concat('V-',_EQUIPMENT_ID,'-',_unit_id,'-', 
			UNIX_TIMESTAMP(DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH)),'-',
			TRIM(_validation_frequency)),
			DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH),
			NULL, 'ACTIVE', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP());*/
            
            ( _SCHEDULE_ID,_unit_id, _EQUIPMENT_ID, 
			concat('V-',_EQUIPMENT_ID,'-',_unit_id,'-', 
			UNIX_TIMESTAMP(DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH)),'-',
			TRIM(_validation_frequency)),
			DATE_ADD(DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH), INTERVAL -1 DAY),
			NULL, 'ACTIVE', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP());
			
            /*  INCREMENT INTERVAL  */            
            SET _LastTestConductedDate = DATE_ADD(_LastTestConductedDate, INTERVAL _FREQUENCYINMONTHS MONTH);
            
		END WHILE;

		/*  UPDATE SCHEDULE GENERATED FLAG IN TEMP TABLE OF EQUIPMENTS  */        
        UPDATE _Equipments SET SCH_GENERATED = 1 WHERE EQUIPMENT_ID=_EQUIPMENT_ID;
        
END WHILE;

/*  SELECT EQUIPMENTS ANALYSIED & INSERTED VALIDATIONS IN SYSTEM  */
-- SELECT * FROM _Equipments; 
-- SELECT * FROM _Equipments_Schedules;

/*  DROP TEMP TABLES  */
-- DROP TEMPORARY TABLE _Equipments;
-- DROP TEMPORARY TABLE _Equipments_Schedules;

-- Restore original SQL mode before ending
SET SESSION sql_mode = original_sql_mode;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `USP_DYNAMIC_GENERATESCHEDULES` (IN `_unit_id` INT, IN `_schedule_year` INT)   BEGIN
-- Store original SQL mode
    DECLARE original_sql_mode VARCHAR(500);
/*  DECLARING VARIABLES  */
DECLARE _Error varchar (200) DEFAULT NULL;
DECLARE _ZeroStart INT DEFAULT 0;
DECLARE _NextValidYear INT DEFAULT 0;
DECLARE _IsValOpen INT DEFAULT 0;

DECLARE _IsReqUnderProcess INT DEFAULT 0;
SET original_sql_mode = @@SESSION.sql_mode;
    
    -- Disable ONLY_FULL_GROUP_BY
    SET SESSION sql_mode = (SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''));


/*  CHECK IF DEFINING VALIDATIONS FOR THE FIRST TIME IN SYSTEM (ZERO START) */
SET _ZeroStart = IF( EXISTS(Select val_sch_id from tbl_val_schedules where unit_id =_unit_id ), 0, 1);

IF _ZeroStart THEN	
		
       SET _IsReqUnderProcess = IF( EXISTS(SELECT  * FROM tbl_val_wf_schedule_requests 
							WHERE unit_id =_unit_id ), 1, 0);
		IF _IsReqUnderProcess THEN
			SET _Error = 'already_exists';
        ELSE
			/*  CALL PROCEDURE CREATESCHEDULES  */
			CALL USP_DYNAMIC_CREATESCHEDULES (_unit_id, _schedule_year);    
			SET _Error = 'success';
        
        END IF;
        
        
    
ELSE

	/*  CHECK NEXT VALID YEAR FOR GENERATING VALIDATIONS  */
	 SELECT MAX(YEAR(val_wf_planned_start_date))+1 FROM tbl_val_schedules WHERE unit_id =_unit_id 
	 AND val_wf_status = 'ACTIVE' INTO _NextValidYear;
  --  SELECT MAX(schedule_year)+1 FROM tbl_val_wf_schedule_requests WHERE unit_id =_unit_id 
-- INTO _NextValidYear;
	
   
		/*  CHECK IF ANY VALIDATION FOR CURRENT YEAR IS OPEN  */
	SET _IsValOpen = IF( EXISTS(        
                            SELECT  val_wf_id FROM tbl_val_schedules 
							WHERE unit_id =_unit_id  AND val_wf_status = 'ACTIVE' 
                            AND YEAR(val_wf_planned_start_date) = _schedule_year 
							AND IsValClosed(tbl_val_schedules.VAL_WF_ID) = FALSE
							), 1, 0);
 
		IF _IsValOpen THEN 
			-- SET _Error = 'PLEASE CLOSE ALL VALIDATIONS OF CURRENT YEAR';
            SET _Error = 'current_year_sch_pending';
           
        ELSE  
			/*  CHECK IF VALIDATION GENERATION REQUEST FOR THE SAME YEAR  */             
			IF _NextValidYear = _schedule_year THEN	
				SET _IsReqUnderProcess = IF( EXISTS(        
                            SELECT  * FROM tbl_val_wf_schedule_requests 
							WHERE unit_id =_unit_id  AND schedule_year = _schedule_year 
							), 1, 0);
		
                IF _IsReqUnderProcess THEN 
            
					SET _Error ='already_exists';
                ELSE
					-- insert into tbl_val_wf_schedule_requests (schedule_year,unit_id) values (_schedule_year,_unit_id);
					/*  CALL PROCEDURE CREATESCHEDULES  */
					 CALL USP_DYNAMIC_CREATESCHEDULES (_unit_id, _schedule_year);
					-- SET _Error = 'SCHEDULED ADDED SUCCESSFULLY...!';
                    SET _Error = 'success';
				END IF;
            
            
            ELSE 
				-- SET _Error = CONCAT('YOU CAN SET SCHEDULE ONLY FOR NEXT YEAR i.e.', CONVERT (_NextValidYear, CHAR(4))); 
                SET _Error = 'invalid_year';
			END IF;
        END IF;
		
	END IF;
    
/*  RETURN ERROR OR SUCCESS MESSAGE  */    
SELECT _Error;
-- Restore original SQL mode before ending
    SET SESSION sql_mode = original_sql_mode;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `USP_ENHANCED_CREATESCHEDULES` (IN `_unit_id` INT, IN `_schedule_year` INT)   main_procedure: BEGIN
    -- Transaction management variables
    DECLARE transaction_rollback BOOL DEFAULT FALSE;
    DECLARE error_message VARCHAR(255) DEFAULT '';
    DECLARE sql_error_code INT DEFAULT 0;
    
    -- Original variables
    DECLARE done INT DEFAULT FALSE;
    DECLARE _equipment_id INT;
    DECLARE _first_validation_date DATE;
    DECLARE _validation_frequencies VARCHAR(50);
    DECLARE _starting_frequency VARCHAR(10);
    DECLARE _last_validation_date DATE;
    DECLARE _next_frequency VARCHAR(10);
    DECLARE _schedule_id INT;
    DECLARE _current_date DATE;
    DECLARE _validation_date DATE;
    DECLARE _val_wf_id VARCHAR(45);
    DECLARE _is_first_validation BOOLEAN;
    DECLARE _has_executed_validations BOOLEAN;
    DECLARE _frequency_count INT DEFAULT 0;
    DECLARE _validation_count INT DEFAULT 0;
    -- Cycle tracking variables
    DECLARE _current_cycle_position INT DEFAULT 0;
    DECLARE _current_cycle_count INT DEFAULT 0;
    DECLARE _cycle_length INT DEFAULT 1;
    
    -- Enhanced cursor for equipment with frequency validation
    DECLARE equipment_cursor CURSOR FOR
        SELECT 
            e.equipment_id,
            e.first_validation_date,
            TRIM(e.validation_frequencies) as validation_frequencies,
            TRIM(e.starting_frequency) as starting_frequency,
            COALESCE(t.last_validation_date, e.first_validation_date) as last_validation_date,
            COALESCE(t.next_frequency, e.starting_frequency) as next_frequency,
            COALESCE(t.cycle_position, 0) as cycle_position,
            COALESCE(t.cycle_count, 0) as cycle_count
        FROM equipments e
        LEFT JOIN equipment_frequency_tracking t ON e.equipment_id = t.equipment_id
        WHERE e.unit_id = _unit_id 
        AND e.equipment_status = 'Active'
        AND e.first_validation_date IS NOT NULL
        AND e.validation_frequencies IS NOT NULL
        AND TRIM(e.validation_frequencies) != ''
        AND e.starting_frequency IS NOT NULL
        AND TRIM(e.starting_frequency) != '';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Exception handling for transactions
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
            sql_error_code = MYSQL_ERRNO,
            error_message = MESSAGE_TEXT;
        SET transaction_rollback = TRUE;
        ROLLBACK;
        SELECT 'error' as result, error_message as error_msg, sql_error_code as error_code;
    END;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Input validation
    IF _unit_id IS NULL OR _unit_id <= 0 THEN
        SET error_message = 'Invalid unit_id provided';
        ROLLBACK;
        SELECT 'error' as result, error_message as error_msg, 0 as error_code;
        LEAVE main_procedure;
    END IF;
    
    IF _schedule_year IS NULL OR _schedule_year < 2020 OR _schedule_year > 2030 THEN
        SET error_message = 'Invalid schedule_year provided (must be between 2020-2030)';
        ROLLBACK;
        SELECT 'error' as result, error_message as error_msg, 0 as error_code;
        LEAVE main_procedure;
    END IF;
    
    -- Create schedule request
    INSERT INTO tbl_val_wf_schedule_requests (schedule_year, unit_id, schedule_generation_datetime) 
    VALUES (_schedule_year, _unit_id, NOW());
    
    SET _schedule_id = LAST_INSERT_ID();
    
    -- Validate that the schedule was created successfully
    IF _schedule_id IS NULL OR _schedule_id <= 0 THEN
        SET error_message = 'Failed to create schedule request';
        ROLLBACK;
        SELECT 'error' as result, error_message as error_msg, 0 as error_code;
        LEAVE main_procedure;
    END IF;
    
    -- Open cursor and process each equipment
    OPEN equipment_cursor;
    
    equipment_loop: LOOP
        FETCH equipment_cursor INTO 
            _equipment_id, _first_validation_date, _validation_frequencies, 
            _starting_frequency, _last_validation_date, _next_frequency,
            _current_cycle_position, _current_cycle_count;
        
        IF done THEN
            LEAVE equipment_loop;
        END IF;
        
        -- Calculate cycle length for this equipment
        SET _cycle_length = GetCycleLength(_validation_frequencies);
        
        -- Validate cycle length
        IF _cycle_length IS NULL OR _cycle_length <= 0 THEN
            SET error_message = CONCAT('Invalid cycle length for equipment_id: ', _equipment_id);
            SET transaction_rollback = TRUE;
            LEAVE equipment_loop;
        END IF;
        
        -- Validate frequency data
        SET _frequency_count = (CHAR_LENGTH(_validation_frequencies) - CHAR_LENGTH(REPLACE(_validation_frequencies, ',', '')) + 1);
        
        -- Skip equipment with invalid frequency configuration
        IF _frequency_count < 1 OR _frequency_count > 5 THEN
            ITERATE equipment_loop;
        END IF;
        
        -- Validate that starting frequency exists in validation frequencies
        IF LOCATE(_starting_frequency, _validation_frequencies) = 0 THEN
            ITERATE equipment_loop;
        END IF;
        
        -- Check if this equipment has executed validations
        SET _has_executed_validations = EXISTS(
            SELECT 1 FROM equipment_frequency_tracking 
            WHERE equipment_id = _equipment_id
        );
        
        -- Determine if this is the first validation
        SET _is_first_validation = NOT _has_executed_validations;
        
        -- Apply business rules based on first validation date and execution status
        IF _is_first_validation THEN
            -- Rule 1: If first validation date is BEFORE target year and no validations executed, SKIP
            IF YEAR(_first_validation_date) < _schedule_year THEN
                ITERATE equipment_loop; 
            END IF;
            
            -- Rule 2: If first validation date is AFTER target year, SKIP  
            IF YEAR(_first_validation_date) > _schedule_year THEN
                ITERATE equipment_loop; 
            END IF;
            
            -- Rule 3: If first validation date is IN target year, INCLUDE
            IF YEAR(_first_validation_date) = _schedule_year THEN
                -- Get frequency at current cycle position using new function
                SET _next_frequency = GetFrequencyAtPosition(_equipment_id, _current_cycle_position);
                
                -- Validate the next frequency
                IF _next_frequency IS NULL OR TRIM(_next_frequency) = '' THEN
                    SET error_message = CONCAT('Unable to determine frequency for equipment_id: ', _equipment_id, ' at position: ', _current_cycle_position);
                    SET transaction_rollback = TRUE;
                    LEAVE equipment_loop;
                END IF;
                
                -- Generate validation workflow ID with frequency
                CALL generate_val_wf_id_with_frequency(_equipment_id, _unit_id, _next_frequency, @_val_wf_id);
                SET _val_wf_id = @_val_wf_id;
                
                -- Validate workflow ID was generated
                IF _val_wf_id IS NULL OR TRIM(_val_wf_id) = '' THEN
                    SET error_message = CONCAT('Failed to generate workflow ID for equipment_id: ', _equipment_id);
                    SET transaction_rollback = TRUE;
                    LEAVE equipment_loop;
                END IF;
                
                -- Insert the first validation schedule using CURRENT cycle state
                INSERT INTO tbl_proposed_val_schedules (
                    schedule_id,
                    unit_id,
                    equip_id,
                    val_wf_id,
                    val_wf_planned_start_date,
                    val_wf_planned_end_date,
                    val_wf_status,
                    frequency_type,
                    created_date_time,
                    last_modified_date_time,
                    cycle_position,
                    cycle_count
                ) VALUES (
                    _schedule_id,
                    _unit_id,
                    _equipment_id,
                    _val_wf_id,
                    _first_validation_date,
                    DATE_ADD(_first_validation_date, INTERVAL 40 DAY),
                    'ACTIVE',
                    _next_frequency,
                    CURRENT_TIMESTAMP(),
                    CURRENT_TIMESTAMP(),
                    _current_cycle_position,
                    _current_cycle_count
                );
                
                SET _current_date = _first_validation_date;
                SET _validation_count = _validation_count + 1;
                
                -- NOW update cycle position for NEXT schedule
                SET _current_cycle_position = (_current_cycle_position + 1) % _cycle_length;
                
                -- Check if cycle completed (wrapped back to 0)
                IF _current_cycle_position = 0 THEN
                    SET _current_cycle_count = _current_cycle_count + 1;
                END IF;
            END IF;
            
        ELSE
            -- Equipment has executed validations - use tracking data
            SET _current_date = _last_validation_date;
        END IF;
        
        -- Generate additional validations for the year
        IF NOT (_is_first_validation AND YEAR(_first_validation_date) != _schedule_year) THEN
            schedule_loop: LOOP
                -- Calculate next validation date using enhanced function
                SET _validation_date = CalculateNextValidationDate(_current_date, _equipment_id);
                
                -- Validate the calculated date
                IF _validation_date IS NULL THEN
                    SET error_message = CONCAT('Failed to calculate next validation date for equipment_id: ', _equipment_id);
                    SET transaction_rollback = TRUE;
                    LEAVE schedule_loop;
                END IF;
                
                -- Exit if we've gone past the target year
                IF YEAR(_validation_date) > _schedule_year THEN
                    LEAVE schedule_loop;
                END IF;
                
                -- Only include validations for the target year (skip first if already added)
                IF YEAR(_validation_date) = _schedule_year AND 
                   NOT (_is_first_validation AND _validation_date = _first_validation_date) THEN
                    
                    -- Get frequency at current cycle position using new function
                    SET _next_frequency = GetFrequencyAtPosition(_equipment_id, _current_cycle_position);
                    
                    -- Validate the next frequency
                    IF _next_frequency IS NULL OR TRIM(_next_frequency) = '' THEN
                        SET _next_frequency = _starting_frequency; -- Fallback
                    END IF;
                    
                    -- Generate validation workflow ID
                    CALL generate_val_wf_id_with_frequency(_equipment_id, _unit_id, _next_frequency, @_val_wf_id);
                    SET _val_wf_id = @_val_wf_id;
                    
                    -- Validate workflow ID was generated
                    IF _val_wf_id IS NULL OR TRIM(_val_wf_id) = '' THEN
                        SET error_message = CONCAT('Failed to generate workflow ID for equipment_id: ', _equipment_id, ' in schedule loop');
                        SET transaction_rollback = TRUE;
                        LEAVE schedule_loop;
                    END IF;
                    
                    -- Insert proposed validation schedule using CURRENT cycle state
                    INSERT INTO tbl_proposed_val_schedules (
                        schedule_id,
                        unit_id,
                        equip_id,
                        val_wf_id,
                        val_wf_planned_start_date,
                        val_wf_planned_end_date,
                        val_wf_status,
                        frequency_type,
                        created_date_time,
                        last_modified_date_time,
                        cycle_position,
                        cycle_count
                    ) VALUES (
                        _schedule_id,
                        _unit_id,
                        _equipment_id,
                        _val_wf_id,
                        _validation_date,
                        DATE_ADD(_validation_date, INTERVAL 40 DAY),
                        'ACTIVE',
                        _next_frequency,
                        CURRENT_TIMESTAMP(),
                        CURRENT_TIMESTAMP(),
                        _current_cycle_position,
                        _current_cycle_count
                    );
                    
                    SET _validation_count = _validation_count + 1;
                    
                    -- NOW update cycle position for NEXT schedule  
                    SET _current_cycle_position = (_current_cycle_position + 1) % _cycle_length;
                    
                    -- Check if cycle completed (wrapped back to 0)
                    IF _current_cycle_position = 0 THEN
                        SET _current_cycle_count = _current_cycle_count + 1;
                    END IF;
                END IF;
                
                -- Update for next iteration
                SET _current_date = _validation_date;
                
                -- Safety checks
                IF (SELECT COUNT(*) FROM tbl_proposed_val_schedules 
                    WHERE schedule_id = _schedule_id AND equip_id = _equipment_id) >= 24 THEN
                    LEAVE schedule_loop; -- Max 24 validations per equipment per year
                END IF;
                
                -- Prevent infinite loops
                IF _validation_count > 1000 THEN
                    SET error_message = CONCAT('Validation count exceeded limit for equipment_id: ', _equipment_id);
                    SET transaction_rollback = TRUE;
                    LEAVE schedule_loop;
                END IF;
                
            END LOOP schedule_loop;
        END IF;
        
        -- Check if we need to rollback due to errors in the schedule loop
        IF transaction_rollback THEN
            LEAVE equipment_loop;
        END IF;
        
        -- Update frequency tracking with the FINAL state for next year
        IF NOT (_is_first_validation AND YEAR(_first_validation_date) != _schedule_year) THEN
            INSERT INTO equipment_frequency_tracking (
                equipment_id, 
                last_validation_date, 
                next_frequency,
                frequency_pattern,
                cycle_position,
                cycle_count,
                last_updated
            ) VALUES (
                _equipment_id, 
                _current_date, 
                _next_frequency,
                _validation_frequencies,
                _current_cycle_position,
                _current_cycle_count,
                NOW()
            ) ON DUPLICATE KEY UPDATE
                last_validation_date = _current_date,
                next_frequency = _next_frequency,
                frequency_pattern = _validation_frequencies,
                cycle_position = _current_cycle_position,
                cycle_count = _current_cycle_count,
                last_updated = NOW();
        END IF;
        
        -- Reset validation counter for next equipment
        SET _validation_count = 0;
        
    END LOOP equipment_loop;
    
    CLOSE equipment_cursor;
    
    -- Final transaction decision
    IF transaction_rollback THEN
        ROLLBACK;
        SELECT 'error' as result, error_message as error_msg, sql_error_code as error_code;
    ELSE
        COMMIT;
        -- Return success with statistics (backward compatible)
        SELECT 'success' as result, _schedule_id as schedule_id;
    END IF;
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `USP_FIXED_CREATESCHEDULES` (IN `_unit_id` INT, IN `_schedule_year` INT)   main_procedure: BEGIN
    -- Transaction management variables
    DECLARE transaction_rollback BOOL DEFAULT FALSE;
    DECLARE error_message VARCHAR(255) DEFAULT '';
    DECLARE sql_error_code INT DEFAULT 0;
    
    -- Original variables
    DECLARE done INT DEFAULT FALSE;
    DECLARE _equipment_id INT;
    DECLARE _first_validation_date DATE;
    DECLARE _validation_frequencies VARCHAR(50);
    DECLARE _starting_frequency VARCHAR(10);
    DECLARE _last_validation_date DATE;
    DECLARE _next_frequency VARCHAR(10);
    DECLARE _schedule_id INT;
    DECLARE _current_date DATE;
    DECLARE _validation_date DATE;
    DECLARE _val_wf_id VARCHAR(45);
    DECLARE _is_first_validation BOOLEAN;
    DECLARE _has_executed_validations BOOLEAN;
    DECLARE _frequency_count INT DEFAULT 0;
    DECLARE _validation_count INT DEFAULT 0;
    -- Cycle tracking variables
    DECLARE _current_cycle_position INT DEFAULT 0;
    DECLARE _current_cycle_count INT DEFAULT 0;
    DECLARE _cycle_length INT DEFAULT 1;
    
    -- Enhanced cursor for equipment with frequency validation
    DECLARE equipment_cursor CURSOR FOR
        SELECT 
            e.equipment_id,
            e.first_validation_date,
            TRIM(e.validation_frequencies) as validation_frequencies,
            TRIM(e.starting_frequency) as starting_frequency,
            COALESCE(t.last_validation_date, e.first_validation_date) as last_validation_date,
            COALESCE(t.next_frequency, e.starting_frequency) as next_frequency,
            COALESCE(t.cycle_position, 0) as cycle_position,
            COALESCE(t.cycle_count, 0) as cycle_count
        FROM equipments e
        LEFT JOIN equipment_frequency_tracking t ON e.equipment_id = t.equipment_id
        WHERE e.unit_id = _unit_id 
        AND e.equipment_status = 'Active'
        AND e.first_validation_date IS NOT NULL
        AND e.validation_frequencies IS NOT NULL
        AND TRIM(e.validation_frequencies) != ''
        AND e.starting_frequency IS NOT NULL
        AND TRIM(e.starting_frequency) != '';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Exception handling for transactions
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
            sql_error_code = MYSQL_ERRNO,
            error_message = MESSAGE_TEXT;
        SET transaction_rollback = TRUE;
        ROLLBACK;
        SELECT 'error' as result, error_message as error_msg, sql_error_code as error_code;
    END;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Input validation
    IF _unit_id IS NULL OR _unit_id <= 0 THEN
        SET error_message = 'Invalid unit_id provided';
        ROLLBACK;
        SELECT 'error' as result, error_message as error_msg, 0 as error_code;
        LEAVE main_procedure;
    END IF;
    
    IF _schedule_year IS NULL OR _schedule_year < 2020 OR _schedule_year > 2030 THEN
        SET error_message = 'Invalid schedule_year provided (must be between 2020-2030)';
        ROLLBACK;
        SELECT 'error' as result, error_message as error_msg, 0 as error_code;
        LEAVE main_procedure;
    END IF;
    
    -- Create schedule request
    INSERT INTO tbl_val_wf_schedule_requests (schedule_year, unit_id, schedule_generation_datetime) 
    VALUES (_schedule_year, _unit_id, NOW());
    
    SET _schedule_id = LAST_INSERT_ID();
    
    -- Validate that the schedule was created successfully
    IF _schedule_id IS NULL OR _schedule_id <= 0 THEN
        SET error_message = 'Failed to create schedule request';
        ROLLBACK;
        SELECT 'error' as result, error_message as error_msg, 0 as error_code;
        LEAVE main_procedure;
    END IF;
    
    -- Open cursor and process each equipment
    OPEN equipment_cursor;
    
    equipment_loop: LOOP
        FETCH equipment_cursor INTO 
            _equipment_id, _first_validation_date, _validation_frequencies, 
            _starting_frequency, _last_validation_date, _next_frequency,
            _current_cycle_position, _current_cycle_count;
        
        IF done THEN
            LEAVE equipment_loop;
        END IF;
        
        -- Calculate cycle length for this equipment
        SET _cycle_length = GetCycleLength(_validation_frequencies);
        
        -- Validate cycle length
        IF _cycle_length IS NULL OR _cycle_length <= 0 THEN
            SET error_message = CONCAT('Invalid cycle length for equipment_id: ', _equipment_id);
            SET transaction_rollback = TRUE;
            LEAVE equipment_loop;
        END IF;
        
        -- Validate frequency data
        SET _frequency_count = (CHAR_LENGTH(_validation_frequencies) - CHAR_LENGTH(REPLACE(_validation_frequencies, ',', '')) + 1);
        
        -- Skip equipment with invalid frequency configuration
        IF _frequency_count < 1 OR _frequency_count > 5 THEN
            ITERATE equipment_loop;
        END IF;
        
        -- Validate that starting frequency exists in validation frequencies
        IF LOCATE(_starting_frequency, _validation_frequencies) = 0 THEN
            ITERATE equipment_loop;
        END IF;
        
        -- Check if this equipment has executed validations
        SET _has_executed_validations = EXISTS(
            SELECT 1 FROM equipment_frequency_tracking 
            WHERE equipment_id = _equipment_id
        );
        
        -- Determine if this is the first validation
        SET _is_first_validation = NOT _has_executed_validations;
        
        -- Apply business rules based on first validation date and execution status
        IF _is_first_validation THEN
            -- Rule 1: If first validation date is BEFORE target year and no validations executed, SKIP
            IF YEAR(_first_validation_date) < _schedule_year THEN
                ITERATE equipment_loop; 
            END IF;
            
            -- Rule 2: If first validation date is AFTER target year, SKIP  
            IF YEAR(_first_validation_date) > _schedule_year THEN
                ITERATE equipment_loop; 
            END IF;
            
            -- Rule 3: If first validation date is IN target year, INCLUDE
            IF YEAR(_first_validation_date) = _schedule_year THEN
                -- Get frequency at current cycle position using new function
                SET _next_frequency = GetFrequencyAtPosition(_equipment_id, _current_cycle_position);
                
                -- Validate the next frequency
                IF _next_frequency IS NULL OR TRIM(_next_frequency) = '' THEN
                    SET error_message = CONCAT('Unable to determine frequency for equipment_id: ', _equipment_id, ' at position: ', _current_cycle_position);
                    SET transaction_rollback = TRUE;
                    LEAVE equipment_loop;
                END IF;
                
                -- Generate validation workflow ID with frequency
                CALL generate_val_wf_id_with_frequency(_equipment_id, _unit_id, _next_frequency, @_val_wf_id);
                SET _val_wf_id = @_val_wf_id;
                
                -- Validate workflow ID was generated
                IF _val_wf_id IS NULL OR TRIM(_val_wf_id) = '' THEN
                    SET error_message = CONCAT('Failed to generate workflow ID for equipment_id: ', _equipment_id);
                    SET transaction_rollback = TRUE;
                    LEAVE equipment_loop;
                END IF;
                
                -- Insert the first validation schedule using CURRENT cycle state
                INSERT INTO tbl_proposed_val_schedules (
                    schedule_id,
                    unit_id,
                    equip_id,
                    val_wf_id,
                    val_wf_planned_start_date,
                    val_wf_planned_end_date,
                    val_wf_status,
                    frequency_type,
                    created_date_time,
                    last_modified_date_time,
                    cycle_position,
                    cycle_count
                ) VALUES (
                    _schedule_id,
                    _unit_id,
                    _equipment_id,
                    _val_wf_id,
                    _first_validation_date,
                    DATE_ADD(_first_validation_date, INTERVAL 40 DAY),
                    'ACTIVE',
                    _next_frequency,
                    CURRENT_TIMESTAMP(),
                    CURRENT_TIMESTAMP(),
                    _current_cycle_position,
                    _current_cycle_count
                );
                
                SET _current_date = _first_validation_date;
                SET _validation_count = _validation_count + 1;
                
                -- NOW update cycle position for NEXT schedule
                SET _current_cycle_position = (_current_cycle_position + 1) % _cycle_length;
                
                -- Check if cycle completed (wrapped back to 0)
                IF _current_cycle_position = 0 THEN
                    SET _current_cycle_count = _current_cycle_count + 1;
                END IF;
            END IF;
            
        ELSE
            -- Equipment has executed validations - use tracking data
            SET _current_date = _last_validation_date;
        END IF;
        
        -- Generate additional validations for the year
        IF NOT (_is_first_validation AND YEAR(_first_validation_date) != _schedule_year) THEN
            schedule_loop: LOOP
                -- Calculate next validation date using enhanced function
                SET _validation_date = CalculateNextValidationDate(_current_date, _equipment_id);
                
                -- Validate the calculated date
                IF _validation_date IS NULL THEN
                    SET error_message = CONCAT('Failed to calculate next validation date for equipment_id: ', _equipment_id);
                    SET transaction_rollback = TRUE;
                    LEAVE schedule_loop;
                END IF;
                
                -- Exit if we've gone past the target year
                IF YEAR(_validation_date) > _schedule_year THEN
                    LEAVE schedule_loop;
                END IF;
                
                -- Only include validations for the target year (skip first if already added)
                IF YEAR(_validation_date) = _schedule_year AND 
                   NOT (_is_first_validation AND _validation_date = _first_validation_date) THEN
                    
                    -- Get frequency at current cycle position using new function
                    SET _next_frequency = GetFrequencyAtPosition(_equipment_id, _current_cycle_position);
                    
                    -- Validate the next frequency
                    IF _next_frequency IS NULL OR TRIM(_next_frequency) = '' THEN
                        SET _next_frequency = _starting_frequency; -- Fallback
                    END IF;
                    
                    -- Generate validation workflow ID
                    CALL generate_val_wf_id_with_frequency(_equipment_id, _unit_id, _next_frequency, @_val_wf_id);
                    SET _val_wf_id = @_val_wf_id;
                    
                    -- Validate workflow ID was generated
                    IF _val_wf_id IS NULL OR TRIM(_val_wf_id) = '' THEN
                        SET error_message = CONCAT('Failed to generate workflow ID for equipment_id: ', _equipment_id, ' in schedule loop');
                        SET transaction_rollback = TRUE;
                        LEAVE schedule_loop;
                    END IF;
                    
                    -- Insert proposed validation schedule using CURRENT cycle state
                    INSERT INTO tbl_proposed_val_schedules (
                        schedule_id,
                        unit_id,
                        equip_id,
                        val_wf_id,
                        val_wf_planned_start_date,
                        val_wf_planned_end_date,
                        val_wf_status,
                        frequency_type,
                        created_date_time,
                        last_modified_date_time,
                        cycle_position,
                        cycle_count
                    ) VALUES (
                        _schedule_id,
                        _unit_id,
                        _equipment_id,
                        _val_wf_id,
                        _validation_date,
                        DATE_ADD(_validation_date, INTERVAL 40 DAY),
                        'ACTIVE',
                        _next_frequency,
                        CURRENT_TIMESTAMP(),
                        CURRENT_TIMESTAMP(),
                        _current_cycle_position,
                        _current_cycle_count
                    );
                    
                    SET _validation_count = _validation_count + 1;
                    
                    -- NOW update cycle position for NEXT schedule  
                    SET _current_cycle_position = (_current_cycle_position + 1) % _cycle_length;
                    
                    -- Check if cycle completed (wrapped back to 0)
                    IF _current_cycle_position = 0 THEN
                        SET _current_cycle_count = _current_cycle_count + 1;
                    END IF;
                END IF;
                
                -- Update for next iteration
                SET _current_date = _validation_date;
                
                -- Safety checks
                IF (SELECT COUNT(*) FROM tbl_proposed_val_schedules 
                    WHERE schedule_id = _schedule_id AND equip_id = _equipment_id) >= 24 THEN
                    LEAVE schedule_loop; -- Max 24 validations per equipment per year
                END IF;
                
                -- Prevent infinite loops
                IF _validation_count > 1000 THEN
                    SET error_message = CONCAT('Validation count exceeded limit for equipment_id: ', _equipment_id);
                    SET transaction_rollback = TRUE;
                    LEAVE schedule_loop;
                END IF;
                
            END LOOP schedule_loop;
        END IF;
        
        -- Check if we need to rollback due to errors in the schedule loop
        IF transaction_rollback THEN
            LEAVE equipment_loop;
        END IF;
        
        -- Update frequency tracking with the FINAL state for next year
        IF NOT (_is_first_validation AND YEAR(_first_validation_date) != _schedule_year) THEN
            INSERT INTO equipment_frequency_tracking (
                equipment_id, 
                last_validation_date, 
                next_frequency,
                frequency_pattern,
                cycle_position,
                cycle_count,
                last_updated
            ) VALUES (
                _equipment_id, 
                _current_date, 
                _next_frequency,
                _validation_frequencies,
                _current_cycle_position,
                _current_cycle_count,
                NOW()
            ) ON DUPLICATE KEY UPDATE
                last_validation_date = _current_date,
                next_frequency = _next_frequency,
                frequency_pattern = _validation_frequencies,
                cycle_position = _current_cycle_position,
                cycle_count = _current_cycle_count,
                last_updated = NOW();
        END IF;
        
        -- Reset validation counter for next equipment
        SET _validation_count = 0;
        
    END LOOP equipment_loop;
    
    CLOSE equipment_cursor;
    
    -- Final transaction decision
    IF transaction_rollback THEN
        ROLLBACK;
        SELECT 'error' as result, error_message as error_msg, sql_error_code as error_code;
    ELSE
        COMMIT;
        -- Return success with statistics (backward compatible)
        SELECT 'success' as result, _schedule_id as schedule_id;
    END IF;
    
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `usp_GenerateRoutineTestSchedules` (IN `_unit_id` INT, IN `_schedule_year` INT)   BEGIN

/*  DECLARING VARIABLES  */
DECLARE _Error varchar (200) DEFAULT NULL;
DECLARE _ZeroStart INT DEFAULT 0;
DECLARE _NextValidYear INT DEFAULT 0;
DECLARE _IsValOpen INT DEFAULT 0;
DECLARE _IsReqUnderProcess INT DEFAULT 0;
/*  CHECK IF DEFINING VALIDATIONS FOR THE FIRST TIME IN SYSTEM (ZERO START) */
SET _ZeroStart = IF( EXISTS(Select routine_test_sch_id from tbl_routine_test_schedules where unit_id =_unit_id ), 0, 1);
IF _ZeroStart THEN	
	
    SET _IsReqUnderProcess = IF( EXISTS(SELECT  * FROM tbl_routine_test_wf_schedule_requests 
							WHERE unit_id =_unit_id ), 1, 0);
		IF _IsReqUnderProcess THEN
			SET _Error = 'already_exists';
        ELSE
			/*  CALL PROCEDURE CREATESCHEDULES  */
			 CALL USP_CREATERTSCHEDULES (_unit_id, _schedule_year);    
			SET _Error = 'success';
        
        END IF;
    
ELSE

	/*  CHECK NEXT VALID YEAR FOR GENERATING VALIDATIONS  */
	SELECT MAX(YEAR(routine_test_wf_planned_start_date))+1 FROM tbl_routine_test_schedules WHERE unit_id =_unit_id 
	AND routine_test_wf_status = 'ACTIVE' INTO _NextValidYear;

		/*  CHECK IF ANY VALIDATION FOR CURRENT YEAR IS OPEN  */
	SET _IsValOpen = IF( EXISTS(        
                            SELECT  routine_test_wf_id FROM tbl_routine_test_schedules 
							WHERE unit_id =_unit_id  AND routine_test_wf_status = 'ACTIVE' 
                            AND YEAR(routine_test_wf_planned_start_date) = _schedule_year 
							AND IsRoutineTestClosed(tbl_routine_test_schedules.routine_test_wf_id) = FALSE
							), 1, 0);
 
		IF _IsValOpen THEN 
			SET _Error = 'current_year_sch_pending';
        ELSE  
			/*  CHECK IF VALIDATION GENERATION REQUEST FOR THE SAME YEAR  */             
			IF _NextValidYear = _schedule_year THEN	
				SET _IsReqUnderProcess = IF( EXISTS(        
                            SELECT  * FROM tbl_routine_test_wf_schedule_requests 
							WHERE unit_id =_unit_id  AND schedule_year = _schedule_year 
							), 1, 0);
		
                IF _IsReqUnderProcess THEN 
            
					SET _Error ='already_exists';
                ELSE
					-- insert into tbl_val_wf_schedule_requests (schedule_year,unit_id) values (_schedule_year,_unit_id);
					/*  CALL PROCEDURE CREATESCHEDULES  */
					 CALL USP_CREATERTSCHEDULES (_unit_id, _schedule_year);
					-- SET _Error = 'SCHEDULED ADDED SUCCESSFULLY...!';
                    SET _Error = 'success';
				END IF;
            
			ELSE 
				SET _Error = 'invalid_year';
			END IF;
        END IF;
		
	END IF;
    
/*  RETURN ERROR OR SUCCESS MESSAGE  */    
SELECT _Error;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `usp_GenerateSchedules` (IN `_unit_id` INT, IN `_schedule_year` INT)   BEGIN

/*  DECLARING VARIABLES  */
DECLARE _Error varchar (200) DEFAULT NULL;
DECLARE _ZeroStart INT DEFAULT 0;
DECLARE _NextValidYear INT DEFAULT 0;
DECLARE _IsValOpen INT DEFAULT 0;

DECLARE _IsReqUnderProcess INT DEFAULT 0;
/*  CHECK IF DEFINING VALIDATIONS FOR THE FIRST TIME IN SYSTEM (ZERO START) */
SET _ZeroStart = IF( EXISTS(Select val_sch_id from tbl_val_schedules where unit_id =_unit_id ), 0, 1);

IF _ZeroStart THEN	
		
       SET _IsReqUnderProcess = IF( EXISTS(SELECT  * FROM tbl_val_wf_schedule_requests 
							WHERE unit_id =_unit_id ), 1, 0);
		IF _IsReqUnderProcess THEN
			SET _Error = 'already_exists';
        ELSE
			/*  CALL PROCEDURE CREATESCHEDULES  */
			CALL USP_CREATESCHEDULES (_unit_id, _schedule_year);    
			SET _Error = 'success';
        
        END IF;
        
        
    
ELSE

	/*  CHECK NEXT VALID YEAR FOR GENERATING VALIDATIONS  */
	 SELECT MAX(YEAR(val_wf_planned_start_date))+1 FROM tbl_val_schedules WHERE unit_id =_unit_id 
	 AND val_wf_status = 'ACTIVE' INTO _NextValidYear;
  --  SELECT MAX(schedule_year)+1 FROM tbl_val_wf_schedule_requests WHERE unit_id =_unit_id 
-- INTO _NextValidYear;
	
   
		/*  CHECK IF ANY VALIDATION FOR CURRENT YEAR IS OPEN  */
	SET _IsValOpen = IF( EXISTS(        
                            SELECT  val_wf_id FROM tbl_val_schedules 
							WHERE unit_id =_unit_id  AND val_wf_status = 'ACTIVE' 
                            AND YEAR(val_wf_planned_start_date) = _schedule_year 
							AND IsValClosed(tbl_val_schedules.VAL_WF_ID) = FALSE
							), 1, 0);
 
		IF _IsValOpen THEN 
			-- SET _Error = 'PLEASE CLOSE ALL VALIDATIONS OF CURRENT YEAR';
            SET _Error = 'current_year_sch_pending';
           
        ELSE  
			/*  CHECK IF VALIDATION GENERATION REQUEST FOR THE SAME YEAR  */             
			IF _NextValidYear = _schedule_year THEN	
				SET _IsReqUnderProcess = IF( EXISTS(        
                            SELECT  * FROM tbl_val_wf_schedule_requests 
							WHERE unit_id =_unit_id  AND schedule_year = _schedule_year 
							), 1, 0);
		
                IF _IsReqUnderProcess THEN 
            
					SET _Error ='already_exists';
                ELSE
					-- insert into tbl_val_wf_schedule_requests (schedule_year,unit_id) values (_schedule_year,_unit_id);
					/*  CALL PROCEDURE CREATESCHEDULES  */
					 CALL USP_DYNAMIC_CREATESCHEDULES (_unit_id, _schedule_year);
					-- SET _Error = 'SCHEDULED ADDED SUCCESSFULLY...!';
                    SET _Error = 'success';
				END IF;
            
            
            ELSE 
				-- SET _Error = CONCAT('YOU CAN SET SCHEDULE ONLY FOR NEXT YEAR i.e.', CONVERT (_NextValidYear, CHAR(4))); 
                SET _Error = 'invalid_year';
			END IF;
        END IF;
		
	END IF;
    
/*  RETURN ERROR OR SUCCESS MESSAGE  */    
SELECT _Error;

END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `USP_InsertApprovalTracking` (IN `p_val_wf_id` VARCHAR(45), IN `p_iteration_start_datetime` DATETIME, IN `p_iteration_completion_status` VARCHAR(45), IN `p_iteration_status` VARCHAR(45), IN `p_engg_app_submission_date_time` DATETIME, IN `p_engg_app_sbmitted_by` VARCHAR(45), IN `p_level1_approver_engg` INT, IN `p_level1_approver_hse` INT, IN `p_level1_approver_qc` INT, IN `p_level1_approver_qa` INT, IN `p_level1_approver_user` INT)   BEGIN
    DECLARE next_iteration_id INT;
    
    -- Check if any records exist for this val_wf_id
    SELECT IFNULL(MAX(iteration_id) + 1, 1) INTO next_iteration_id
    FROM tbl_val_wf_approval_tracking_details
    WHERE val_wf_id = p_val_wf_id;
    
    INSERT INTO `tbl_report_approvers`
(`val_wf_id`,
`iteration_id`,
`level1_approver_engg`,
`level1_approver_hse`,
`level1_approver_qc`,
`level1_approver_qa`,
`level1_approver_user`)
VALUES
(p_val_wf_id,
next_iteration_id,
p_level1_approver_engg,
p_level1_approver_hse,
p_level1_approver_qc,
p_level1_approver_qa,
p_level1_approver_user);

    
    -- Insert the new record with the calculated iteration_id
    INSERT INTO tbl_val_wf_approval_tracking_details (
        val_wf_id,
        iteration_id,
        iteration_start_datetime,
        iteration_completion_status,
        iteration_status,
        engg_app_submission_date_time,
        engg_app_sbmitted_by
        
    ) VALUES (
        p_val_wf_id,
        next_iteration_id,
        p_iteration_start_datetime,
        p_iteration_completion_status,
        p_iteration_status,
        p_engg_app_submission_date_time,
        p_engg_app_sbmitted_by
        
    );
    
    -- Return the iteration_id that was used
    SELECT next_iteration_id AS used_iteration_id;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `CalculateNextValidationDate` (`base_date` DATE, `equipment_id` INT) RETURNS DATE DETERMINISTIC READS SQL DATA BEGIN
    DECLARE validation_freqs VARCHAR(10);
    DECLARE interval_months INT;

    -- Get validation frequencies for the equipment
    SELECT validation_frequencies INTO validation_freqs
    FROM equipments
    WHERE equipments.equipment_id = equipment_id;

    -- Get the actual validation interval (minimum for dual frequencies)
    SET interval_months = GetValidationIntervalMonths(validation_freqs);

    -- Calculate next validation date
    RETURN DATE_SUB(DATE_ADD(base_date, INTERVAL interval_months MONTH), INTERVAL 1 DAY);
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_enddate_months` (`inputdate` DATE, `freqinmonths` INT) RETURNS DATE DETERMINISTIC BEGIN
 DECLARE intdate DATE;
  DECLARE enddate DATE;
  select DATE_ADD(inputdate, INTERVAL freqinmonths MONTH) into intdate;
  
    IF day(intdate) = day(inputdate) THEN
      SET enddate = date_sub(intdate,interval 1 day);

   ELSE
      SET enddate = intdate;

   END IF;

   RETURN enddate;
  
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_is_auto_schedule_enabled` (`p_type` VARCHAR(20)) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_enabled BOOLEAN DEFAULT FALSE;
    DECLARE v_config_value VARCHAR(10);
    
    SELECT config_value INTO v_config_value
    FROM auto_schedule_config
    WHERE config_key = CASE 
        WHEN p_type = 'validation' THEN 'validation_auto_schedule_enabled'
        WHEN p_type = 'routine' THEN 'routine_test_auto_schedule_enabled'
        ELSE 'auto_schedule_disabled'
    END;
    
    IF v_config_value = 'Y' THEN
        SET v_enabled = TRUE;
    END IF;
    
    RETURN v_enabled;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `fn_validate_frequency` (`p_frequency` VARCHAR(5)) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
    -- Valid frequencies are Q, H, Y, 2Y (not HY)
    RETURN p_frequency IN ('Q', 'H', 'Y', '2Y');
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `GetCycleLength` (`validation_frequencies` VARCHAR(50)) RETURNS INT DETERMINISTIC BEGIN
    DECLARE total_freqs INT DEFAULT 1;
    DECLARE has_6m BOOLEAN DEFAULT FALSE;
    
    -- Handle single frequency
    IF LOCATE(',', validation_frequencies) = 0 THEN
        RETURN 1;
    END IF;
    
    -- Count total frequencies
    SET total_freqs = (CHAR_LENGTH(validation_frequencies) - CHAR_LENGTH(REPLACE(validation_frequencies, ',', '')) + 1);
    
    -- Check if 6M is present
    SET has_6m = (LOCATE('6M', validation_frequencies) > 0);
    
    -- Determine cycle length based on pattern
    IF total_freqs = 2 THEN
        -- Dual frequencies: simple 2-position cycle (e.g., 6M,Y or Y,2Y)
        RETURN 2;
    ELSEIF total_freqs = 3 AND has_6m THEN
        -- Triple frequencies with 6M: use 4-position alternating cycle
        -- Pattern: start_freq -> 6M -> other_freq -> 6M (4 positions)
        RETURN 4;
    ELSE
        -- Other triple frequencies or more: use total count
        RETURN total_freqs;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `GetFrequencyAtPosition` (`equipment_id` INT, `cycle_position` INT) RETURNS VARCHAR(10) CHARSET utf8mb4 DETERMINISTIC READS SQL DATA BEGIN
    DECLARE validation_freqs VARCHAR(50);
    DECLARE starting_freq VARCHAR(10);
    DECLARE total_freqs INT DEFAULT 0;
    DECLARE temp_freq VARCHAR(10);
    DECLARE remaining_freqs VARCHAR(50);
    DECLARE comma_pos INT;
    DECLARE has_6m BOOLEAN DEFAULT FALSE;
    DECLARE non_6m_freq1 VARCHAR(10) DEFAULT '';
    DECLARE non_6m_freq2 VARCHAR(10) DEFAULT '';
    
    -- Get validation frequencies and starting frequency
    SELECT 
        e.validation_frequencies,
        e.starting_frequency
    INTO validation_freqs, starting_freq
    FROM equipments e
    WHERE e.equipment_id = equipment_id;
    
    -- Handle single frequency (no cycling needed)
    IF LOCATE(',', validation_freqs) = 0 THEN
        RETURN validation_freqs;
    END IF;
    
    -- Parse frequencies to identify pattern
    SET remaining_freqs = validation_freqs;
    SET total_freqs = 0;
    
    parse_frequencies:WHILE LENGTH(remaining_freqs) > 0 DO
        SET comma_pos = LOCATE(',', remaining_freqs);
        
        IF comma_pos = 0 THEN
            SET temp_freq = TRIM(remaining_freqs);
            SET remaining_freqs = '';
        ELSE
            SET temp_freq = TRIM(SUBSTRING(remaining_freqs, 1, comma_pos - 1));
            SET remaining_freqs = TRIM(SUBSTRING(remaining_freqs, comma_pos + 1));
        END IF;
        
        SET total_freqs = total_freqs + 1;
        
        IF temp_freq = '6M' THEN
            SET has_6m = TRUE;
        ELSE
            -- Store non-6M frequencies in order
            IF non_6m_freq1 = '' THEN
                SET non_6m_freq1 = temp_freq;
            ELSEIF non_6m_freq2 = '' THEN
                SET non_6m_freq2 = temp_freq;
            END IF;
        END IF;
        
        -- Safety check
        IF total_freqs > 10 THEN
            LEAVE parse_frequencies;
        END IF;
    END WHILE parse_frequencies;
    
    -- Handle triple frequencies with 6M (alternating pattern)
    IF total_freqs = 3 AND has_6m THEN
        -- 4-position alternating cycle based on starting frequency
        
        IF starting_freq = non_6m_freq1 THEN
            -- Starting with first non-6M (e.g., Y in 6M,Y,2Y)
            -- Pattern: Y(0) → 6M(1) → 2Y(2) → 6M(3) → Y(0)...
            IF cycle_position % 4 = 0 THEN
                RETURN non_6m_freq1; -- Y
            ELSEIF cycle_position % 4 = 1 THEN
                RETURN '6M';          -- 6M
            ELSEIF cycle_position % 4 = 2 THEN
                RETURN non_6m_freq2; -- 2Y
            ELSE
                RETURN '6M';          -- 6M
            END IF;
            
        ELSEIF starting_freq = non_6m_freq2 THEN
            -- Starting with second non-6M (e.g., 2Y in 6M,Y,2Y)  
            -- Pattern: 2Y(0) → 6M(1) → Y(2) → 6M(3) → 2Y(0)...
            IF cycle_position % 4 = 0 THEN
                RETURN non_6m_freq2; -- 2Y
            ELSEIF cycle_position % 4 = 1 THEN
                RETURN '6M';          -- 6M
            ELSEIF cycle_position % 4 = 2 THEN
                RETURN non_6m_freq1; -- Y
            ELSE
                RETURN '6M';          -- 6M
            END IF;
            
        ELSE
            -- Starting frequency is 6M
            -- Pattern: 6M(0) → Y(1) → 6M(2) → 2Y(3) → 6M(0)...
            IF cycle_position % 4 = 0 THEN
                RETURN '6M';          -- 6M
            ELSEIF cycle_position % 4 = 1 THEN
                RETURN non_6m_freq1; -- Y
            ELSEIF cycle_position % 4 = 2 THEN
                RETURN '6M';          -- 6M
            ELSE
                RETURN non_6m_freq2; -- 2Y
            END IF;
        END IF;
        
    ELSE
        -- For dual frequencies or other patterns, use simple sequential cycling
        -- Convert comma-separated string to array-like access
        SET remaining_freqs = validation_freqs;
        SET comma_pos = 0;
        
        -- Skip to the position we want
        WHILE comma_pos < (cycle_position % total_freqs) DO
            SET temp_freq = LOCATE(',', remaining_freqs);
            IF temp_freq > 0 THEN
                SET remaining_freqs = SUBSTRING(remaining_freqs, temp_freq + 1);
            END IF;
            SET comma_pos = comma_pos + 1;
        END WHILE;
        
        -- Get the frequency at this position
        SET temp_freq = LOCATE(',', remaining_freqs);
        IF temp_freq > 0 THEN
            RETURN TRIM(SUBSTRING(remaining_freqs, 1, temp_freq - 1));
        ELSE
            RETURN TRIM(remaining_freqs);
        END IF;
    END IF;
    
    -- Fallback
    RETURN starting_freq;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `GetFrequencyIntervalMonths` (`frequency` VARCHAR(3)) RETURNS INT DETERMINISTIC BEGIN
    RETURN CASE frequency
        WHEN '6M' THEN 6
        WHEN 'Y' THEN 12
        WHEN '2Y' THEN 24
        ELSE 12
    END;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `GetValidationIntervalMonths` (`validation_frequencies` VARCHAR(50)) RETURNS INT DETERMINISTIC READS SQL DATA BEGIN
    DECLARE freq_count INT DEFAULT 0;
    DECLARE min_interval INT DEFAULT 12; -- Default to yearly
    DECLARE current_freq VARCHAR(10);
    DECLARE remaining_freqs VARCHAR(50);
    DECLARE comma_pos INT;
    DECLARE freq_interval INT;
    
    -- Handle single frequency
    IF LOCATE(',', validation_frequencies) = 0 THEN
        RETURN GetFrequencyIntervalMonths(validation_frequencies);
    END IF;
    
    -- Parse multiple frequencies and find minimum interval
    SET remaining_freqs = validation_frequencies;
    
    parse_loop: WHILE LENGTH(remaining_freqs) > 0 DO
        SET comma_pos = LOCATE(',', remaining_freqs);
        
        IF comma_pos = 0 THEN
            -- Last frequency in the list
            SET current_freq = TRIM(remaining_freqs);
            SET remaining_freqs = '';
        ELSE
            -- Extract current frequency
            SET current_freq = TRIM(SUBSTRING(remaining_freqs, 1, comma_pos - 1));
            SET remaining_freqs = TRIM(SUBSTRING(remaining_freqs, comma_pos + 1));
        END IF;
        
        -- Get interval for current frequency
        SET freq_interval = GetFrequencyIntervalMonths(current_freq);
        
        -- Track minimum interval
        IF freq_interval < min_interval THEN
            SET min_interval = freq_interval;
        END IF;
        
        SET freq_count = freq_count + 1;
        
        -- Safety check to prevent infinite loops
        IF freq_count > 10 THEN
            LEAVE parse_loop;
        END IF;
    END WHILE parse_loop;
    
    RETURN min_interval;
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `IsRoutineTestClosed` (`_routine_test_wf_id` VARCHAR(50)) RETURNS TINYINT(1) DETERMINISTIC BEGIN

/*  DECALRE VARIABLES  */
   DECLARE ROUTINETESTSTATUS INT DEFAULT 0;
   DECLARE PRIMARYTESTID INT DEFAULT 0;

/*  CHECK IF ANY VALIDATIONS EXISTS FOR THE VALIDATION ID  */   
	If NOT EXISTS (SELECT routine_test_wf_id From tbl_routine_test_wf_tracking_details where routine_test_wf_id = _routine_test_wf_id) 
    THEN RETURN FALSE; END IF;

/*  CHECK IF ANY TESTS EXISTS FOR THE VALIDATION ID  */
	If NOT EXISTS (SELECT test_sch_id From tbl_test_schedules_tracking where val_wf_id = _routine_test_wf_id) 
    THEN RETURN FALSE; END IF;

/*  FETCH STATUS OF VALIDATION FROM VALIDATION TABLE FOR THE VALIDATION ID  */
	SELECT routine_test_wf_current_stage INTO ROUTINETESTSTATUS From tbl_routine_test_wf_tracking_details 
	Where routine_test_wf_id = _routine_test_wf_id;

/*  CHECK IF VALIDATION STATUS FROM VALIDATION TABLE FOR THE VALIDATION ID IS COMPLETED */	
    IF ROUTINETESTSTATUS = 5 THEN RETURN TRUE; END IF;

/*  FETCH THE PRIMARY TEST FROM UNITS TABLE FOR THE EQUIPMENT ID  */
	SELECT B.primary_test_id INTO PRIMARYTESTID FROM tbl_routine_test_wf_tracking_details A JOIN units B 
    where A.unit_id = B.UNIT_ID AND A.routine_test_wf_id = _routine_test_wf_id;

/*  FETCH STATUS OF PRIMARY TEST FROM TEST TABLE FOR THE VALIDATION ID  */
	SELECT test_wf_current_stage INTO ROUTINETESTSTATUS From tbl_test_schedules_tracking 
	Where val_wf_id = _routine_test_wf_id And test_id = PRIMARYTESTID;

/*  CHECK IF THE STATUS OF PRIMARY TEST FROM TEST TABLE FOR THE VALIDATION ID IS COMPLETED  */ 
    IF ROUTINETESTSTATUS = 5 THEN RETURN TRUE; END IF;
            
	RETURN FALSE;
    
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `IsValClosed` (`_val_wf_id` VARCHAR(50)) RETURNS TINYINT(1) DETERMINISTIC BEGIN

/*  DECALRE VARIABLES  */
   DECLARE VALSTATUS INT DEFAULT 0;
   DECLARE PRIMARYTESTID INT DEFAULT 0;

/*  CHECK IF ANY VALIDATIONS EXISTS FOR THE VALIDATION ID  */   
	If NOT EXISTS (SELECT val_wf_id From tbl_val_wf_tracking_details where val_wf_id = _val_wf_id) 
    THEN RETURN FALSE; END IF;

/*  CHECK IF ANY TESTS EXISTS FOR THE VALIDATION ID  */
	If NOT EXISTS (SELECT test_sch_id From tbl_test_schedules_tracking where val_wf_id = _val_wf_id) 
    THEN RETURN FALSE; END IF;

/*  FETCH STATUS OF VALIDATION FROM VALIDATION TABLE FOR THE VALIDATION ID  */
	SELECT val_wf_current_stage INTO VALSTATUS From tbl_val_wf_tracking_details 
	Where val_wf_id = _val_wf_id;

/*  CHECK IF VALIDATION STATUS FROM VALIDATION TABLE FOR THE VALIDATION ID IS COMPLETED */	
    IF VALSTATUS = 5 THEN RETURN TRUE; END IF;

/*  FETCH THE PRIMARY TEST FROM UNITS TABLE FOR THE EQUIPMENT ID  */
	SELECT B.primary_test_id INTO PRIMARYTESTID FROM tbl_val_wf_tracking_details A JOIN units B 
    where A.unit_id = B.UNIT_ID AND A.val_wf_id = _val_wf_id;

/*  FETCH STATUS OF PRIMARY TEST FROM TEST TABLE FOR THE VALIDATION ID  */
	SELECT test_wf_current_stage INTO VALSTATUS From tbl_test_schedules_tracking 
	Where val_wf_id = _val_wf_id And test_id = PRIMARYTESTID;

/*  CHECK IF THE STATUS OF PRIMARY TEST FROM TEST TABLE FOR THE VALIDATION ID IS COMPLETED  */ 
    IF VALSTATUS = 5 THEN RETURN TRUE; END IF;
            
	RETURN FALSE;
    
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `approver_remarks`
--

CREATE TABLE `approver_remarks` (
  `remarks_id` int NOT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `test_wf_id` varchar(50) DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `val_wf_id` varchar(50) DEFAULT NULL,
  `master_table_name` varchar(100) DEFAULT NULL,
  `master_ref_id` varchar(45) DEFAULT NULL,
  `action` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `approver_remarks`
--

INSERT INTO `approver_remarks` (`remarks_id`, `remarks`, `test_wf_id`, `user_id`, `created_date_time`, `val_wf_id`, `master_table_name`, `master_ref_id`, `action`) VALUES
(24698, 'ok', '', 42, '2025-10-11 11:45:01', '', NULL, NULL, NULL),
(24699, 'ok', '', 44, '2025-10-11 11:46:11', '', NULL, NULL, NULL),
(24700, 'ok', '', 41, '2025-10-11 11:46:37', '', NULL, NULL, NULL),
(24701, 'ok', '', 42, '2025-10-11 12:02:07', '', NULL, NULL, NULL),
(24702, 'Task submitted', 'T-1-7-2-1760164349', 74, '2025-10-11 12:22:43', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24703, 'Rucha TA', 'T-1-7-2-1760164349', 42, '2025-10-11 12:29:52', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24704, 'ok', 'T-1-7-2-1760164349', 46, '2025-10-11 12:32:55', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24705, 'Task Submitted RD', 'T-1-7-1-1760164349', 74, '2025-10-11 12:46:08', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24706, 'ok', 'T-1-7-1-1760164349', 42, '2025-10-11 12:47:31', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24707, 'ok', 'T-1-7-1-1760164349', 46, '2025-10-11 12:49:36', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24708, 'ok', 'T-1-7-9-1760164349', 42, '2025-10-11 12:58:41', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24709, 'ok', 'T-1-7-3-1760164349', 74, '2025-10-11 17:23:44', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24710, 'ok', 'T-1-7-6-1760164349', 74, '2025-10-11 17:24:29', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24711, 'ok', 'T-1-7-3-1760164349', 42, '2025-10-11 17:25:34', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24712, 'ok', 'T-1-7-6-1760164349', 42, '2025-10-11 17:26:13', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24713, 'ok', 'T-1-7-3-1760164349', 46, '2025-10-11 17:27:08', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24714, 'ok', 'T-1-7-6-1760164349', 46, '2025-10-11 17:27:43', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24715, 'ok', '', 42, '2025-10-11 17:31:08', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24716, 'ok', '', 42, '2025-10-11 17:31:52', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24717, 'ok', '', 54, '2025-10-11 17:32:56', 'V-1-7-1760163302-6M', NULL, NULL, NULL),
(24718, 'ok', '', 41, '2025-10-11 17:33:35', 'V-1-7-1760163302-6M', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `audit_trail_id` int NOT NULL,
  `val_wf_id` varchar(50) DEFAULT NULL,
  `test_wf_id` varchar(50) DEFAULT NULL,
  `user_id` varchar(45) DEFAULT NULL,
  `user_type` varchar(45) DEFAULT NULL,
  `time_stamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `wf_stage` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`audit_trail_id`, `val_wf_id`, `test_wf_id`, `user_id`, `user_type`, `time_stamp`, `wf_stage`) VALUES
(29048, 'V-1-7-1760163302-6M', '', '42', NULL, '2025-10-11 12:02:29', '1'),
(29049, 'V-1-7-1760163302-6M', 'T-1-7-1-1760164349', '42', NULL, '2025-10-11 12:02:29', '1'),
(29050, 'V-1-7-1760163302-6M', 'T-1-7-2-1760164349', '42', NULL, '2025-10-11 12:02:29', '1'),
(29051, 'V-1-7-1760163302-6M', 'T-1-7-3-1760164349', '42', NULL, '2025-10-11 12:02:29', '1'),
(29052, 'V-1-7-1760163302-6M', 'T-1-7-6-1760164349', '42', NULL, '2025-10-11 12:02:29', '1'),
(29053, 'V-1-7-1760163302-6M', 'T-1-7-9-1760164349', '42', NULL, '2025-10-11 12:02:29', '1'),
(29054, 'V-1-7-1760163302-6M', '', '42', 'employee', '2025-10-11 12:02:29', '1'),
(29055, 'V-1-7-1760163302-6M', 'T-1-7-2-1760164349', '74', 'vendor', '2025-10-11 12:22:43', '2'),
(29056, 'V-1-7-1760163302-6M', 'T-1-7-2-1760164349', '42', 'employee', '2025-10-11 12:29:52', '3A'),
(29057, 'V-1-7-1760163302-6M', 'T-1-7-2-1760164349', '46', 'employee', '2025-10-11 12:32:55', '5'),
(29058, 'V-1-7-1760163302-6M', 'T-1-7-1-1760164349', '74', 'vendor', '2025-10-11 12:46:08', '2'),
(29059, 'V-1-7-1760163302-6M', 'T-1-7-1-1760164349', '42', 'employee', '2025-10-11 12:47:31', '3A'),
(29060, 'V-1-7-1760163302-6M', 'T-1-7-1-1760164349', '46', 'employee', '2025-10-11 12:49:36', '5'),
(29061, 'V-1-7-1760163302-6M', 'T-1-7-9-1760164349', '42', 'employee', '2025-10-11 12:58:41', '5'),
(29062, 'V-1-7-1760163302-6M', 'T-1-7-3-1760164349', '74', 'vendor', '2025-10-11 17:23:44', '2'),
(29063, 'V-1-7-1760163302-6M', 'T-1-7-6-1760164349', '74', 'vendor', '2025-10-11 17:24:29', '2'),
(29064, 'V-1-7-1760163302-6M', 'T-1-7-3-1760164349', '42', 'employee', '2025-10-11 17:25:34', '3A'),
(29065, 'V-1-7-1760163302-6M', 'T-1-7-6-1760164349', '42', 'employee', '2025-10-11 17:26:13', '3A'),
(29066, 'V-1-7-1760163302-6M', 'T-1-7-3-1760164349', '46', 'employee', '2025-10-11 17:27:08', '5'),
(29067, 'V-1-7-1760163302-6M', 'T-1-7-6-1760164349', '46', 'employee', '2025-10-11 17:27:43', '5'),
(29068, 'V-1-7-1760163302-6M', '', '42', 'employee', '2025-10-11 17:31:54', '3'),
(29069, 'V-1-7-1760163302-6M', '', '54', 'employee', '2025-10-11 17:32:57', '4'),
(29070, 'V-1-7-1760163302-6M', '', '41', 'employee', '2025-10-11 17:33:37', '5');

-- --------------------------------------------------------

--
-- Table structure for table `auto_schedule_config`
--

CREATE TABLE `auto_schedule_config` (
  `config_id` int NOT NULL,
  `config_key` varchar(50) NOT NULL,
  `config_value` varchar(100) NOT NULL,
  `description` text,
  `last_modified` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `auto_schedule_config`
--

INSERT INTO `auto_schedule_config` (`config_id`, `config_key`, `config_value`, `description`, `last_modified`) VALUES
(1, 'validation_auto_schedule_enabled', 'Y', 'Enable auto-scheduling for validation studies', '2025-07-29 13:32:10'),
(2, 'routine_test_auto_schedule_enabled', 'Y', 'Enable auto-scheduling for routine tests', '2025-08-10 23:11:54'),
(3, 'validation_supported_frequencies', 'Y,2Y', 'Enhanced validation frequencies: Annual and Bi-annual with sophisticated logic', '2025-07-29 13:45:37'),
(4, 'routine_test_supported_frequencies', 'Q,H,Y,2Y', 'Enhanced routine test frequencies with H1/H2 awareness', '2025-07-29 16:13:51'),
(5, 'auto_schedule_suffix', 'A', 'Suffix for auto-created validation workflow IDs', '2025-07-29 13:32:10'),
(6, 'max_auto_schedule_depth', '5', 'Maximum number of cascading auto-schedules', '2025-07-29 13:32:10'),
(7, 'debug_logging_enabled', 'Y', 'Enable detailed debug logging', '2025-07-29 13:32:10'),
(8, 'auto_schedule_system_version', '1.0', 'Auto-scheduling system version', '2025-07-29 13:32:55'),
(15, 'enhanced_validation_frequencies_enabled', 'Y', 'Enable enhanced frequency support for validations', '2025-07-29 13:42:02'),
(16, 'bi_annual_validation_support', 'Y', 'Support for 2Y (bi-annual) validation auto-scheduling', '2025-07-29 13:42:02'),
(17, 'enhanced_half_yearly_logic_enabled', 'Y', 'Enable sophisticated H1/H2 awareness for half-yearly tests', '2025-07-29 13:43:16'),
(18, 'half_yearly_pattern_analysis', 'Y', 'Enable pattern analysis for half-yearly scheduling', '2025-07-29 13:43:16'),
(19, 'calendar_aware_scheduling', 'Y', 'Enable calendar-aware scheduling algorithms', '2025-07-29 13:43:16'),
(21, 'enhanced_half_yearly_logic', 'Y', 'Enable sophisticated H1/H2 half-yearly logic', '2025-07-29 13:45:37'),
(22, 'edge_case_handling', 'Y', 'Enable comprehensive edge case handling', '2025-07-29 13:45:37'),
(23, 'frequency_pattern_analysis', 'Y', 'Enable frequency pattern analysis for scheduling', '2025-07-29 13:45:37'),
(26, 'enhanced_workflow_id_generation', 'Y', 'Enable enhanced workflow ID generation with frequency-suffix appending', '2025-07-29 13:55:28'),
(27, 'workflow_id_pattern_validation', 'Y', 'Enable workflow ID pattern validation', '2025-07-29 13:55:28'),
(28, 'id_collision_handling_enhanced', 'Y', 'Enhanced ID collision handling with frequency-based progression', '2025-07-29 13:55:28'),
(29, 'enhanced_routine_workflow_id_generation', 'Y', 'Enable enhanced routine test workflow ID generation with frequency-suffix appending', '2025-07-29 13:56:34'),
(30, 'routine_id_pattern_validation', 'Y', 'Enable routine test workflow ID pattern validation', '2025-07-29 13:56:34'),
(31, 'early_execution_logic_enabled', 'Y', 'Frequency compliance priority - always reschedule based on actual execution regardless of timing variance', '2025-08-10 02:07:38'),
(32, 'early_execution_log_details', 'Y', 'Log detailed information about early vs late execution decisions', '2025-08-10 01:32:16'),
(33, 'frequency_compliance_enabled', 'Y', 'Enable frequency compliance priority - maintain consistent test intervals regardless of execution timing', '2025-08-10 02:07:38'),
(34, 'frequency_gap_prevention', 'Y', 'Prevent large gaps in routine test schedules by always rescheduling based on actual execution', '2025-08-10 02:07:38'),
(35, 'schedule_change_audit_enabled', 'Y', 'Enable comprehensive audit logging for routine test schedule changes', '2025-08-10 02:34:26'),
(36, 'schedule_change_audit_retention_days', '2555', 'Number of days to retain schedule change audit records (7 years)', '2025-08-10 02:34:26'),
(37, 'schedule_change_detail_logging', 'Y', 'Log detailed information in schedule change audit table', '2025-08-10 02:34:26'),
(38, 'test_origin_null_default', 'Y', 'Use NULL as default for test_origin column - represents system_original behavior', '2025-08-10 10:24:35'),
(39, 'test_origin_explicit_user_manual_only', 'Y', 'Only user_manual_adhoc is explicitly set, NULL/system_auto_created handled by system', '2025-08-10 10:24:35');

-- --------------------------------------------------------

--
-- Table structure for table `auto_schedule_deployment_backup`
--

CREATE TABLE `auto_schedule_deployment_backup` (
  `backup_id` int NOT NULL,
  `deployment_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `backup_type` varchar(50) DEFAULT NULL,
  `backup_data` text,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auto_schedule_log`
--

CREATE TABLE `auto_schedule_log` (
  `log_id` int NOT NULL,
  `trigger_type` varchar(50) NOT NULL COMMENT 'validation_auto_created, routine_test_updated, etc.',
  `trigger_timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `original_id` varchar(50) DEFAULT NULL COMMENT 'Original validation/routine test ID',
  `equipment_id` int DEFAULT NULL COMMENT 'Equipment involved',
  `test_id` int DEFAULT NULL COMMENT 'Test that triggered the action',
  `original_execution_date` date DEFAULT NULL COMMENT 'When test was actually executed',
  `original_planned_date` date DEFAULT NULL COMMENT 'Originally planned date',
  `calculated_date` date DEFAULT NULL COMMENT 'Calculated next date',
  `new_id` varchar(50) DEFAULT NULL COMMENT 'New auto-created ID (if applicable)',
  `action_taken` varchar(100) DEFAULT NULL COMMENT 'Description of action taken',
  `days_optimized` int DEFAULT NULL COMMENT 'Days saved/adjusted',
  `frequency` varchar(10) DEFAULT NULL COMMENT 'Test frequency',
  `status` varchar(50) DEFAULT 'success' COMMENT 'success, no_action_needed, error',
  `error_details` text COMMENT 'Error details if status = error',
  `business_rule_applied` varchar(100) DEFAULT NULL COMMENT 'Which business rule was applied',
  `notes` text COMMENT 'Additional notes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `auto_schedule_log`
--

INSERT INTO `auto_schedule_log` (`log_id`, `trigger_type`, `trigger_timestamp`, `original_id`, `equipment_id`, `test_id`, `original_execution_date`, `original_planned_date`, `calculated_date`, `new_id`, `action_taken`, `days_optimized`, `frequency`, `status`, `error_details`, `business_rule_applied`, `notes`) VALUES
(88, 'validation_test_selection', '2025-08-28 15:38:47', 'V-1-7-1756214655-A', NULL, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'no_action_needed', 'Completed test is not the target test for auto-scheduling', 'test_selection_logic', NULL),
(89, 'validation_test_selection', '2025-08-28 15:44:09', 'V-1-7-1756217319-A', NULL, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'no_action_needed', 'Completed test is not the target test for auto-scheduling', 'test_selection_logic', NULL),
(90, 'validation_year_check', '2025-10-08 16:20:55', 'V-3-7-1759903625-Y', 3, 1, '2025-10-08', NULL, NULL, NULL, NULL, NULL, 'Y', 'no_action_needed', 'Execution year: 2025, Validation current year: 2025', 'year_comparison_logic', NULL),
(91, 'validation_year_check', '2025-10-08 18:06:22', 'V-1-7-1759903526-6M', 1, 1, '2025-10-08', NULL, NULL, NULL, NULL, NULL, 'Y', 'no_action_needed', 'Execution year: 2025, Validation current year: 2025', 'year_comparison_logic', NULL),
(92, 'validation_test_selection', '2025-10-11 12:32:55', 'V-1-7-1760163302-6M', NULL, 2, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'no_action_needed', 'Completed test is not the target test for auto-scheduling', 'test_selection_logic', NULL),
(93, 'validation_year_check', '2025-10-11 12:49:36', 'V-1-7-1760163302-6M', 1, 1, '2025-10-11', NULL, NULL, NULL, NULL, NULL, 'Y', 'no_action_needed', 'Execution year: 2025, Validation current year: 2025', 'year_comparison_logic', NULL),
(94, 'validation_test_selection', '2025-10-11 17:27:08', 'V-1-7-1760163302-6M', NULL, 3, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'no_action_needed', 'Completed test is not the target test for auto-scheduling', 'test_selection_logic', NULL),
(95, 'validation_test_selection', '2025-10-11 17:27:43', 'V-1-7-1760163302-6M', NULL, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'no_action_needed', 'Completed test is not the target test for auto-scheduling', 'test_selection_logic', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `demotbl`
--

CREATE TABLE `demotbl` (
  `id` int NOT NULL,
  `file_name` varchar(500) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` varchar(45) DEFAULT NULL,
  `department` varchar(45) DEFAULT NULL,
  `employee` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `department_status` enum('Active','Inactive') DEFAULT 'Active',
  `department_created_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `department_modified_datetime` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `department_status`, `department_created_datetime`, `department_modified_datetime`) VALUES
(0, 'Quality Control', 'Active', '2020-10-21 23:32:32', '2020-10-21 23:32:32'),
(1, 'Engineering', 'Active', '2020-09-13 16:34:33', '2020-09-13 16:34:33'),
(2, 'Production', 'Active', '2020-09-25 22:35:23', '2020-09-25 22:35:23'),
(3, 'Packing', 'Active', '2020-09-25 22:36:59', '2020-09-25 22:36:59'),
(4, 'Stores', 'Active', '2020-09-25 22:36:59', '2020-09-25 22:36:59'),
(5, 'Topical', 'Active', '2020-09-25 22:36:59', '2020-09-25 22:36:59'),
(6, 'Microbiology', 'Active', '2020-09-25 22:36:59', '2020-09-25 22:36:59'),
(7, 'EHS', 'Active', '2020-09-25 22:36:59', '2020-09-25 22:36:59'),
(8, 'Quality assurance', 'Active', '2020-09-25 22:36:59', '2020-09-25 22:36:59'),
(9, 'Heads', 'Active', '2020-09-26 13:17:37', '2020-09-26 13:17:37'),
(10, 'Mediaclave', 'Active', NULL, '2024-03-05 11:04:07'),
(11, 'Information Technology', 'Active', NULL, '2024-06-28 09:11:27'),
(12, 'Coating', 'Active', NULL, '2024-09-05 00:25:42'),
(13, 'Topical Manufacturing', 'Active', NULL, '2024-09-05 00:25:42'),
(14, 'Compression', 'Active', NULL, '2024-09-05 00:25:42'),
(15, 'Granulation', 'Active', NULL, '2024-09-05 00:25:42'),
(16, 'Topical Packing', 'Active', NULL, '2024-09-05 00:25:42');

-- --------------------------------------------------------

--
-- Table structure for table `equipments`
--

CREATE TABLE `equipments` (
  `equipment_id` int NOT NULL,
  `equipment_code` varchar(45) DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `equipment_category` varchar(45) DEFAULT NULL,
  `validation_frequency` enum('Q','H','Y','2Y','') DEFAULT 'Y',
  `area_served` varchar(500) DEFAULT NULL,
  `section` varchar(100) DEFAULT NULL,
  `design_acph` varchar(100) DEFAULT NULL,
  `area_classification` varchar(100) DEFAULT NULL,
  `area_classification_in_operation` varchar(100) DEFAULT NULL,
  `equipment_type` varchar(100) DEFAULT NULL,
  `design_cfm` varchar(100) DEFAULT NULL,
  `filteration_fresh_air` varchar(100) DEFAULT NULL,
  `filteration_pre_filter` varchar(100) DEFAULT NULL,
  `filteration_intermediate` varchar(100) DEFAULT NULL,
  `filteration_final_filter_plenum` varchar(100) DEFAULT NULL,
  `filteration_exhaust_pre_filter` varchar(100) DEFAULT NULL,
  `filteration_exhaust_final_filter` varchar(100) DEFAULT NULL,
  `filteration_terminal_filter` varchar(100) DEFAULT NULL,
  `filteration_terminal_filter_on_riser` varchar(100) DEFAULT NULL,
  `filteration_bibo_filter` varchar(100) DEFAULT NULL,
  `filteration_relief_filter` varchar(100) DEFAULT NULL,
  `filteration_reativation_filter` varchar(100) DEFAULT NULL,
  `equipment_status` enum('Active','Inactive') DEFAULT 'Active',
  `equipment_creation_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `equipment_last_modification_datetime` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `equipment_addition_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `validation_frequencies` varchar(10) NOT NULL DEFAULT 'Y',
  `starting_frequency` enum('6M','Y','2Y') NOT NULL DEFAULT 'Y',
  `first_validation_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipments`
--

INSERT INTO `equipments` (`equipment_id`, `equipment_code`, `unit_id`, `department_id`, `equipment_category`, `validation_frequency`, `area_served`, `section`, `design_acph`, `area_classification`, `area_classification_in_operation`, `equipment_type`, `design_cfm`, `filteration_fresh_air`, `filteration_pre_filter`, `filteration_intermediate`, `filteration_final_filter_plenum`, `filteration_exhaust_pre_filter`, `filteration_exhaust_final_filter`, `filteration_terminal_filter`, `filteration_terminal_filter_on_riser`, `filteration_bibo_filter`, `filteration_relief_filter`, `filteration_reativation_filter`, `equipment_status`, `equipment_creation_datetime`, `equipment_last_modification_datetime`, `equipment_addition_date`, `validation_frequencies`, `starting_frequency`, `first_validation_date`) VALUES
(1, 'AHU-01', 7, 2, 'AHD', 'Y', 'Packing corridor, Production corridor, Compression-Coating corridor, IPQC III, Change room-III', 'Quality', '20', 'ISO-2008', 'Not defined', 'Recirculating', '8429', 'Treated fresh air through 3µ filter provided', 'EU-6', 'NA', 'EU-13', 'NA', 'NA', 'NA', 'NA', 'NA', 'NA234', 'NA', 'Active', NULL, '2025-09-14 19:36:58', '2024-04-12 00:00:00', '6M,Y', 'Y', '2025-09-14'),
(2, 'AHU-02', 7, 4, 'AHU', '2Y', 'Raw material store-I, Raw material store III, Reject, Dedusting, Change room (R.M receiving bay), R.M corridor, External corridor', 'R.M stores', '20', 'Controlled not classified', 'unclassified', 'Recirculating', '13700', 'Treated fresh air through 3µ filter provided', 'EU-6', 'NA', 'EU-8', 'NA', 'NA', 'NA', 'NA', 'NA', 'NA', 'NA', 'Active', NULL, '2025-09-19 12:21:35', '2023-08-16 00:00:00', '6M,Y', 'Y', '2025-02-07'),
(3, 'AHU-03', 7, 2, 'AHU', 'Y', 'Blending', 'Granulation', '30', 'ISO-8', 'Not defined', 'Recirculating', '1658', 'Treated fresh air through 3µ filter provided', 'EU-4', 'EU-6', 'EU-8', 'NA', 'NA', 'EU-13', 'EU-4', 'EU-13', 'EU-13', 'EU-4', 'Active', NULL, '2025-09-20 13:46:26', '2024-01-11 00:00:00', 'Y,2Y', 'Y', '2025-09-03'),
(4, 'AHU-04', 7, 2, 'AHU', 'Y', 'Compression I', 'Compression', '30', 'ISO-8', 'Not defined', 'Recirculating', '675', 'Treated fresh air through 3µ filter provided', 'EU-4', 'EU-6', 'EU-8', 'NA', 'NA', 'EU-13', 'EU-4', 'EU-13', 'EU-13', 'EU-4', 'Active', NULL, '2025-09-29 18:35:53', '2025-01-30 00:00:00', '6M,Y', 'Y', '2025-09-08'),
(5, 'AHU-06', 7, 2, 'AHU', 'Y', 'Coating I', 'Coating', '30', 'ISO-8', 'Not defined', 'Recirculating', '2240', 'Treated fresh air through 3µ filter provided', 'EU-6', 'NA', 'EU-8', 'NA', 'NA', 'EU-13', 'NA', 'NA', 'EU-13', 'NA', 'Active', NULL, '2025-09-20 13:46:46', '2024-10-23 00:00:00', '6M,Y,2Y', 'Y', '2025-09-20'),
(6, 'AHU-09', 7, 3, 'AHU', 'Y', 'Packing Cubicle-I', 'Packing', '30', 'ISO-8', 'Not defined', 'Recirculating', '1500', 'Treated fresh air through 3µ filter provided', 'NA', 'NA', 'EU-8', 'NA', 'NA', 'EU-13', 'EU-4', 'EU-13', 'EU-13', 'NA', 'Active', NULL, '2025-09-23 10:12:07', '2024-09-13 00:00:00', '2Y', '2Y', '2024-03-03');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_frequency_tracking`
--

CREATE TABLE `equipment_frequency_tracking` (
  `tracking_id` int NOT NULL,
  `equipment_id` int NOT NULL,
  `last_validation_date` date DEFAULT NULL,
  `next_frequency` enum('6M','Y','2Y') NOT NULL,
  `created_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_modified_datetime` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `frequency_pattern` varchar(50) DEFAULT NULL,
  `cycle_position` int DEFAULT '0',
  `cycle_count` int DEFAULT '0',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipment_frequency_tracking`
--

INSERT INTO `equipment_frequency_tracking` (`tracking_id`, `equipment_id`, `last_validation_date`, `next_frequency`, `created_datetime`, `last_modified_datetime`, `frequency_pattern`, `cycle_position`, `cycle_count`, `last_updated`) VALUES
(126, 1, '2025-09-14', '6M', '2025-10-11 11:45:01', '2025-10-11 11:45:01', '6M,Y', 1, 0, '2025-10-11 06:15:01'),
(127, 2, '2025-08-06', 'Y', '2025-10-11 11:45:01', '2025-10-11 11:45:01', '6M,Y', 0, 1, '2025-10-11 06:15:01'),
(128, 3, '2025-09-03', 'Y', '2025-10-11 11:45:01', '2025-10-11 11:45:01', 'Y,2Y', 1, 0, '2025-10-11 06:15:01'),
(129, 4, '2025-09-08', '6M', '2025-10-11 11:45:01', '2025-10-11 11:45:01', '6M,Y', 1, 0, '2025-10-11 06:15:01'),
(130, 5, '2025-09-20', 'Y', '2025-10-11 11:45:01', '2025-10-11 11:45:01', '6M,Y,2Y', 1, 0, '2025-10-11 06:15:01');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_test_vendor_mapping`
--

CREATE TABLE `equipment_test_vendor_mapping` (
  `mapping_id` int NOT NULL,
  `equipment_id` int NOT NULL,
  `test_id` int NOT NULL,
  `test_type` varchar(45) NOT NULL,
  `frequency_label` varchar(3) NOT NULL DEFAULT 'ALL',
  `vendor_id` int NOT NULL,
  `mapping_status` varchar(45) DEFAULT 'Active',
  `vendor_created_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `vendor_last_modification_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipment_test_vendor_mapping`
--

INSERT INTO `equipment_test_vendor_mapping` (`mapping_id`, `equipment_id`, `test_id`, `test_type`, `frequency_label`, `vendor_id`, `mapping_status`, `vendor_created_datetime`, `vendor_last_modification_datetime`) VALUES
(3850, 1, 1, 'Validation_Test', '6M', 3, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3851, 1, 2, 'Validation_Test', 'ALL', 3, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3852, 1, 3, 'Validation_Test', 'ALL', 3, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3853, 1, 6, 'Validation_Test', 'ALL', 3, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3854, 1, 9, 'Validation_Test', 'ALL', 0, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3855, 1, 10, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3856, 1, 11, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3857, 1, 12, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3858, 1, 14, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3859, 1, 15, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3861, 2, 2, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3862, 2, 3, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3863, 2, 4, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3864, 2, 6, 'Validation_Test', 'ALL', 3, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3865, 2, 8, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3866, 2, 9, 'Validation_Test', 'ALL', 0, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3867, 3, 1, 'Validation_Test', 'ALL', 3, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3868, 3, 2, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3869, 3, 3, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3870, 3, 4, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3871, 3, 6, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3872, 3, 8, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3873, 3, 9, 'Validation_Test', 'ALL', 0, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3874, 3, 10, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3875, 3, 11, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3876, 3, 12, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3877, 3, 14, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3878, 3, 15, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3879, 4, 1, 'Validation_Test', 'ALL', 3, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3880, 4, 2, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3881, 4, 3, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3882, 4, 4, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3883, 4, 6, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3884, 4, 7, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3885, 4, 8, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3886, 4, 9, 'Validation_Test', 'ALL', 0, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3887, 4, 10, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3888, 4, 11, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3889, 4, 12, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3890, 4, 14, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3891, 4, 15, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3892, 5, 1, 'Validation_Test', 'ALL', 3, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3893, 5, 2, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3894, 5, 3, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3895, 5, 4, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3896, 5, 6, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3897, 5, 8, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3898, 5, 9, 'Validation_Test', 'ALL', 0, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3899, 5, 10, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3900, 5, 11, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3901, 5, 12, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3902, 5, 14, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3903, 5, 15, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3904, 6, 1, 'Validation_Test', 'ALL', 3, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3905, 6, 2, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3906, 6, 3, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3907, 6, 4, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3908, 6, 6, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3909, 6, 7, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3910, 6, 8, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3911, 6, 9, 'Validation_Test', 'ALL', 0, 'Active', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3912, 6, 10, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3913, 6, 11, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3914, 6, 12, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3915, 6, 14, 'Validation_Test', 'ALL', 3, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3916, 6, 15, 'Validation_Test', 'ALL', 0, 'Inactive', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(8114, 1, 16, 'Validation_Test', 'ALL', 0, 'Inactive', '2025-01-02 22:14:08', '2025-01-02 22:14:08'),
(8115, 2, 16, 'Validation_Test', 'ALL', 0, 'Inactive', '2025-01-02 22:14:08', '2025-01-02 22:14:08'),
(8116, 3, 16, 'Validation_Test', 'ALL', 0, 'Inactive', '2025-01-02 22:14:08', '2025-01-02 22:14:08'),
(8117, 4, 16, 'Validation_Test', 'ALL', 0, 'Inactive', '2025-01-02 22:14:08', '2025-01-02 22:14:08'),
(8118, 5, 16, 'Validation_Test', 'ALL', 0, 'Inactive', '2025-01-02 22:14:08', '2025-01-02 22:14:08'),
(8119, 6, 16, 'Validation_Test', 'ALL', 0, 'Inactive', '2025-01-02 22:14:08', '2025-01-02 22:14:08'),
(9237, 2, 1, 'Validation_Test', 'ALL', 1, 'Active', '2025-09-02 11:07:31', '2025-09-02 11:07:31'),
(9242, 2, 1, 'Validation_Test', 'Y', 3, 'Active', '2025-09-02 11:21:22', '2025-09-02 11:21:22');

-- --------------------------------------------------------

--
-- Table structure for table `erf_mappings`
--

CREATE TABLE `erf_mappings` (
  `erf_mapping_id` int NOT NULL COMMENT 'Unique identifier for ERF mapping',
  `equipment_id` int NOT NULL COMMENT 'Foreign key to equipments table',
  `room_loc_id` int NOT NULL COMMENT 'Foreign key to room_locations table',
  `area_classification` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N/A' COMMENT 'Area classification of the equipment',
  `filter_id` int DEFAULT NULL COMMENT 'References filter_id from filters table',
  `filter_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Filter name/description',
  `filter_group_id` int DEFAULT NULL COMMENT 'Foreign key to filter_groups table (optional)',
  `erf_mapping_status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active' COMMENT 'Status of the mapping',
  `creation_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `last_modification_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ERF Mapping Master - Equipment Room Filter relationships';

--
-- Dumping data for table `erf_mappings`
--

INSERT INTO `erf_mappings` (`erf_mapping_id`, `equipment_id`, `room_loc_id`, `area_classification`, `filter_id`, `filter_name`, `filter_group_id`, `erf_mapping_status`, `creation_datetime`, `last_modification_datetime`) VALUES
(1, 1, 1, 'ISO 5/Grade B', 1, 'AHU-01/THF/0.3mu/01/A', 1, 'Active', '2025-09-04 22:41:20', '2025-09-08 03:09:23'),
(2, 1, 1, 'ISO 5/Grade &apos;B&apos;', 2, 'AHU-01/THF/0.3mu/02/A', 1, 'Active', '2025-09-04 22:41:20', '2025-09-08 03:10:09'),
(3, 3, 1, 'ISO5/Grade B', 1, 'AHU-01/THF/0.3mu/01/A', 1, 'Active', '2025-10-08 14:51:39', '2025-10-08 14:51:39'),
(4, 4, 1, 'ISO 5/Grade B', 1, 'AHU-01/THF/0.3mu/01/A', 1, 'Active', '2025-10-08 23:38:08', '2025-10-08 23:38:08');

-- --------------------------------------------------------

--
-- Table structure for table `error_log`
--

CREATE TABLE `error_log` (
  `id` int NOT NULL,
  `error_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `error_message` text,
  `equip_id` int DEFAULT NULL,
  `current_val_wf_id` varchar(45) DEFAULT NULL,
  `current_unit_id` int DEFAULT NULL,
  `current_planned_start_date` datetime DEFAULT NULL,
  `operation_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `filters`
--

CREATE TABLE `filters` (
  `filter_id` int NOT NULL,
  `unit_id` int DEFAULT NULL,
  `filter_code` varchar(100) NOT NULL,
  `filter_name` varchar(255) DEFAULT NULL,
  `filter_size` enum('Standard','Large','Small','Custom') DEFAULT 'Standard',
  `filter_type_id` int DEFAULT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `specifications` text,
  `installation_date` date NOT NULL,
  `planned_due_date` date DEFAULT NULL,
  `actual_replacement_date` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `creation_datetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_modification_datetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `filters`
--

INSERT INTO `filters` (`filter_id`, `unit_id`, `filter_code`, `filter_name`, `filter_size`, `filter_type_id`, `manufacturer`, `specifications`, `installation_date`, `planned_due_date`, `actual_replacement_date`, `status`, `creation_datetime`, `last_modification_datetime`, `created_by`) VALUES
(1, 7, 'AHU-01/THF/0.3mu/01/A', 'Terminal HEPA Filter 01A', 'Large', 1, 'ABC Filtration Inc', 'H14 grade, 99.995% efficiency at 0.3μm', '2024-01-15', '2026-04-30', NULL, 'Active', '2025-09-07 19:45:10', '2025-09-14 14:34:13', 1),
(2, 7, 'AHU-01/THF/0.3mu/02/A', 'Terminal HEPA Filter 02A', 'Standard', 1, 'ABC Filtration Inc', 'H14 grade, 99.995% efficiency at 0.3μm', '2024-01-15', '2025-01-15', NULL, 'Active', '2025-09-07 19:45:10', '2025-09-07 20:02:39', 1),
(3, 7, 'AHU-02/PRE/01/B', 'Pre-Filter AHU-02', 'Large', 2, 'FilterTech Ltd', 'G4 grade pre-filter', '2024-02-01', '2024-08-01', NULL, 'Active', '2025-09-07 19:45:10', '2025-09-07 20:02:39', 1),
(4, 7, 'AHU-03/ULP/0.12mu/01/C', 'ULPA Filter AHU-03', 'Standard', 3, 'Ultra Clean Filters', 'U15 grade, 99.9995% efficiency at 0.12μm', '2024-03-01', '2025-03-01', NULL, 'Active', '2025-09-07 19:45:10', '2025-09-07 20:02:39', 1),
(5, 7, 'EXHAUST-01/CARB/01/A', 'Carbon Filter Exhaust 01', 'Custom', 4, 'Carbon Solutions', 'Activated carbon for odor control', '2024-01-20', '2024-10-20', NULL, 'Inactive', '2025-09-07 19:45:10', '2025-09-07 20:02:39', 1),
(8, 7, 'AHU-01/THF/0.3mu/03/A', 'Terminal Hepa Filter', 'Standard', 1, 'Camfil', '', '2025-09-08', '2026-01-31', NULL, 'Active', '2025-09-07 19:55:50', '2025-09-07 20:02:39', 2050);

-- --------------------------------------------------------

--
-- Table structure for table `filter_groups`
--

CREATE TABLE `filter_groups` (
  `filter_group_id` int NOT NULL COMMENT 'Unique identifier for filter group',
  `filter_group_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name/description of the filter group',
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active' COMMENT 'Status of the filter group',
  `creation_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
  `last_modification_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification timestamp'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Filter Groups Master - Categories for filter classification';

--
-- Dumping data for table `filter_groups`
--

INSERT INTO `filter_groups` (`filter_group_id`, `filter_group_name`, `status`, `creation_datetime`, `last_modification_datetime`) VALUES
(1, 'HEPA Filters', 'Active', '2025-09-04 23:59:38', '2025-09-04 23:59:38'),
(2, 'Pre-Filters', 'Active', '2025-09-04 23:59:38', '2025-09-05 00:19:34'),
(3, 'Terminal Filters', 'Active', '2025-09-04 23:59:38', '2025-09-04 23:59:38'),
(4, 'FFU', 'Active', '2025-09-04 23:59:38', '2025-09-05 00:17:54'),
(5, 'Final Filters', 'Active', '2025-09-04 23:59:38', '2025-09-04 23:59:38'),
(6, 'Intermediate Filters', 'Active', '2025-09-04 23:59:38', '2025-09-04 23:59:38'),
(9, 'AHU', 'Active', '2025-09-05 00:26:01', '2025-09-05 00:26:01');

-- --------------------------------------------------------

--
-- Table structure for table `frequency_intervals`
--

CREATE TABLE `frequency_intervals` (
  `frequency_code` varchar(5) NOT NULL,
  `description` varchar(50) NOT NULL,
  `interval_months` int NOT NULL,
  `interval_sql` varchar(20) NOT NULL COMMENT 'SQL interval expression'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `frequency_intervals`
--

INSERT INTO `frequency_intervals` (`frequency_code`, `description`, `interval_months`, `interval_sql`) VALUES
('2Y', 'Bi-Annual', 24, 'INTERVAL 2 YEAR'),
('H', 'Half-Yearly', 6, 'INTERVAL 6 MONTH'),
('HY', 'Half-Yearly', 6, 'INTERVAL 6 MONTH'),
('Q', 'Quarterly', 3, 'INTERVAL 3 MONTH'),
('Y', 'Annual', 12, 'INTERVAL 1 YEAR');

-- --------------------------------------------------------

--
-- Table structure for table `instruments`
--

CREATE TABLE `instruments` (
  `instrument_id` varchar(100) NOT NULL COMMENT 'Instrument ID/TAG Number',
  `instrument_type` enum('Air Capture Hood','Anmometer','Photometer','Particle Counter') NOT NULL COMMENT 'Instrument Type',
  `vendor_id` int NOT NULL COMMENT 'Valid Vendor ID from Vendor Master',
  `serial_number` varchar(100) NOT NULL COMMENT 'Serial Number',
  `calibrated_on` datetime DEFAULT NULL COMMENT 'Calibrated On',
  `calibration_due_on` datetime DEFAULT NULL COMMENT 'Calibration Due On',
  `master_certificate_path` varchar(100) DEFAULT NULL COMMENT 'Master Certificate PDF Path',
  `created_date` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Created Date',
  `created_by` int DEFAULT NULL COMMENT 'Valid User ID from User Master',
  `reviewed_date` datetime DEFAULT NULL COMMENT 'Reviewed Date',
  `reviewed_by` int DEFAULT NULL COMMENT 'Valid User ID from User Master',
  `instrument_status` enum('Active','Inactive','Pending') NOT NULL DEFAULT 'Pending',
  `approval_status` enum('APPROVED','PENDING_APPROVAL','DRAFT') DEFAULT 'APPROVED' COMMENT 'Current approval status of instrument record',
  `pending_approval_id` int DEFAULT NULL COMMENT 'Reference to pending approval record',
  `submitted_by` int DEFAULT NULL COMMENT 'User ID who submitted/modified the record',
  `checker_id` int DEFAULT NULL COMMENT 'User ID who performed checker approval/rejection',
  `checker_action` enum('Approved','Rejected') DEFAULT NULL COMMENT 'Checker decision',
  `checker_date` datetime DEFAULT NULL COMMENT 'Date and time of checker action',
  `checker_remarks` text COMMENT 'Checker comments/remarks',
  `original_data` json DEFAULT NULL COMMENT 'Original data before modification for audit trail'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `instruments`
--

INSERT INTO `instruments` (`instrument_id`, `instrument_type`, `vendor_id`, `serial_number`, `calibrated_on`, `calibration_due_on`, `master_certificate_path`, `created_date`, `created_by`, `reviewed_date`, `reviewed_by`, `instrument_status`, `approval_status`, `pending_approval_id`, `submitted_by`, `checker_id`, `checker_action`, `checker_date`, `checker_remarks`, `original_data`) VALUES
('INs234', 'Air Capture Hood', 1, 's222', '2025-09-02 00:00:00', '2026-09-02 00:00:00', 'uploads/certificates/cert_INs234_1756763772.pdf', '2025-09-02 02:47:20', 2050, '2025-09-02 03:26:12', 2050, 'Active', 'APPROVED', NULL, 41, NULL, NULL, NULL, NULL, NULL),
('INs2346777', 'Air Capture Hood', 1, 's2228', '2025-09-02 00:00:00', '2026-09-02 00:00:00', 'uploads/certificates/cert_INs2346777_1756761660.pdf', '2025-09-02 02:51:00', 2050, NULL, NULL, 'Active', 'APPROVED', NULL, 41, NULL, NULL, NULL, NULL, NULL),
('INs2346777sss', 'Air Capture Hood', 1, 's2228', '2025-09-02 00:00:00', '2026-09-02 00:00:00', 'uploads/certificates/cert_INs2346777sss_1756761827.pdf', '2025-09-02 02:53:47', 2050, NULL, NULL, 'Active', 'APPROVED', NULL, 41, NULL, NULL, NULL, NULL, NULL),
('INST001', 'Air Capture Hood', 1, 'SN001', '2024-01-01 00:00:00', '2025-01-01 00:00:00', 'uploads/certificates/cert_INST001_1757506406.pdf', '2025-09-02 02:13:10', NULL, '2025-09-10 17:53:20', 2050, 'Inactive', 'APPROVED', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
('INST002', 'Photometer', 1, 'SN002', '2023-01-01 00:00:00', '2024-01-01 00:00:00', NULL, '2025-09-02 02:13:10', NULL, NULL, NULL, 'Active', 'APPROVED', NULL, 41, NULL, NULL, NULL, NULL, NULL),
('INST003', 'Particle Counter', 3, 'SN003', '2024-12-01 00:00:00', '2025-12-15 00:00:00', NULL, '2025-09-02 02:13:10', NULL, NULL, NULL, 'Active', 'APPROVED', NULL, 41, NULL, NULL, NULL, NULL, NULL),
('NewTest', 'Anmometer', 1, 'S12345', '2025-09-02 00:00:00', '2026-09-02 00:00:00', 'uploads/certificates/cert_NewTest_1756762487.pdf', '2025-09-02 02:54:12', 2050, '2025-09-02 03:04:47', 2050, 'Active', 'APPROVED', NULL, 41, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `instrument_approval_audit`
--

CREATE TABLE `instrument_approval_audit` (
  `audit_id` int NOT NULL,
  `approval_id` int NOT NULL,
  `action_type` enum('SUBMITTED','APPROVED','REJECTED','RESUBMITTED') NOT NULL,
  `performed_by` int NOT NULL,
  `performed_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `remarks` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Audit trail for instrument approval activities';

-- --------------------------------------------------------

--
-- Table structure for table `instrument_calibration_approvals`
--

CREATE TABLE `instrument_calibration_approvals` (
  `approval_id` int NOT NULL,
  `instrument_id` varchar(100) NOT NULL COMMENT 'Instrument ID being modified',
  `workflow_action` enum('CREATE','MODIFY') NOT NULL COMMENT 'Type of action',
  `approval_status` enum('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING' COMMENT 'Current approval status',
  `created_by_vendor_user` int NOT NULL COMMENT 'User who initiated the change',
  `created_datetime` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'When change was initiated',
  `draft_data` json DEFAULT NULL COMMENT 'Stores pending changes in JSON format',
  `original_certificate_path` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded certificate file',
  `reviewed_by_vendor_user` int DEFAULT NULL COMMENT 'User who reviewed the change',
  `reviewed_datetime` datetime DEFAULT NULL COMMENT 'When review was completed',
  `reviewer_remarks` text COMMENT 'Reviewer comments',
  `vendor_id` int NOT NULL COMMENT 'Vendor ID for access control',
  `created_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_modified_date` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `instrument_certificate_history`
--

CREATE TABLE `instrument_certificate_history` (
  `history_id` int NOT NULL,
  `instrument_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `certificate_file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `calibrated_on` date NOT NULL,
  `calibration_due_on` date NOT NULL,
  `uploaded_by` int NOT NULL,
  `uploaded_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  `file_size` bigint DEFAULT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `instrument_certificate_history`
--

INSERT INTO `instrument_certificate_history` (`history_id`, `instrument_id`, `certificate_file_path`, `calibrated_on`, `calibration_due_on`, `uploaded_by`, `uploaded_date`, `is_active`, `file_size`, `original_filename`, `notes`) VALUES
(1, 'NewTest', 'uploads/certificates/cert_NewTest_1756762453.pdf', '2025-09-02', '2026-09-02', 2050, '2025-09-01 21:34:13', 0, 9711, 'Document.pdf', 'Certificate uploaded via instrument update'),
(2, 'NewTest', 'uploads/certificates/cert_NewTest_1756762487.pdf', '2025-09-02', '2026-09-02', 2050, '2025-09-01 21:34:47', 1, 1556265, 'India Salary Guide 2025 Michael Page India.pdf', 'Certificate uploaded via instrument update'),
(3, 'INs234', 'uploads/certificates/cert_INs234_1756763772.pdf', '2025-09-02', '2026-09-02', 2050, '2025-09-01 21:56:12', 1, 1556265, 'India Salary Guide 2025 Michael Page India (1).pdf', 'Certificate uploaded via instrument update'),
(4, 'INST001', 'uploads/certificates/cert_INST001_1757506406.pdf', '2024-01-01', '2025-01-01', 2050, '2025-09-10 12:13:26', 1, 9711, 'Document.pdf', 'Certificate uploaded via instrument update');

-- --------------------------------------------------------

--
-- Table structure for table `instrument_workflow_log`
--

CREATE TABLE `instrument_workflow_log` (
  `log_id` int NOT NULL,
  `instrument_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_type` enum('Created','Modified','Approved','Rejected','Resubmitted') COLLATE utf8mb4_unicode_ci NOT NULL,
  `performed_by` int NOT NULL,
  `action_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `old_data` json DEFAULT NULL,
  `new_data` json DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for instrument workflow actions';

--
-- Dumping data for table `instrument_workflow_log`
--

INSERT INTO `instrument_workflow_log` (`log_id`, `instrument_id`, `action_type`, `performed_by`, `action_date`, `old_data`, `new_data`, `remarks`, `ip_address`, `user_agent`) VALUES
(1, 'INs234', 'Created', 41, '2025-09-02 02:47:20', NULL, NULL, 'Historical record - migrated during workflow implementation', NULL, NULL),
(2, 'INs2346777', 'Created', 41, '2025-09-02 02:51:00', NULL, NULL, 'Historical record - migrated during workflow implementation', NULL, NULL),
(3, 'INs2346777sss', 'Created', 41, '2025-09-02 02:53:47', NULL, NULL, 'Historical record - migrated during workflow implementation', NULL, NULL),
(4, 'INST002', 'Created', 41, '2025-09-02 02:13:10', NULL, NULL, 'Historical record - migrated during workflow implementation', NULL, NULL),
(5, 'INST003', 'Created', 41, '2025-09-02 02:13:10', NULL, NULL, 'Historical record - migrated during workflow implementation', NULL, NULL),
(6, 'NewTest', 'Created', 41, '2025-09-02 02:54:12', NULL, NULL, 'Historical record - migrated during workflow implementation', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `log`
--

CREATE TABLE `log` (
  `log_id` int NOT NULL,
  `change_type` varchar(45) DEFAULT NULL,
  `table_name` varchar(45) DEFAULT NULL,
  `change_description` varchar(200) DEFAULT NULL,
  `change_by` int DEFAULT NULL,
  `change_datetime` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `unit_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `log`
--

INSERT INTO `log` (`log_id`, `change_type`, `table_name`, `change_description`, `change_by`, `change_datetime`, `unit_id`) VALUES
(67838, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 11:44:27', 7),
(67839, 'add_remarks_success', 'approver_remarks', 'Remarks added by user engg_user_one (Remarks ID: 24698) - Schedule generation completed successfully.', 42, '2025-10-11 11:45:01', 7),
(67840, 'tran_vsch_gen', '', 'Validation schedule Generated. User ID:42 Sch ID:141 UnitID:7 Logic:fixed Year:2025', 42, '2025-10-11 11:45:02', 7),
(67841, 'tran_view_schedule', '', 'User Engg User One viewed schedule PDF: uploads/schedule-report-7-141.pdf', 42, '2025-10-11 11:45:26', 7),
(67842, 'tran_logout', '', 'User engg_user_one has logged out.', 42, '2025-10-11 11:45:37', 7),
(67843, 'tran_login_int_emp', '', 'User engg_head_one logged into the system.', 44, '2025-10-11 11:45:46', 7),
(67844, 'add_remarks_success', 'approver_remarks', 'Remarks added by user engg_head_one (Remarks ID: 24699) - Authentication completed successfully.', 44, '2025-10-11 11:46:11', 7),
(67845, 'tran_vsch_app_eng', '', 'Validation schedule approved by Eng Dept Head. SchID:141', 44, '2025-10-11 11:46:11', 7),
(67846, 'tran_logout', '', 'User engg_head_one has logged out.', 44, '2025-10-11 11:46:15', 7),
(67847, 'tran_login_int_emp', '', 'User qa_head_one logged into the system.', 41, '2025-10-11 11:46:23', 7),
(67848, 'add_remarks_success', 'approver_remarks', 'Remarks added by user qa_head_one (Remarks ID: 24700) - Authentication completed successfully.', 41, '2025-10-11 11:46:37', 7),
(67849, 'tran_vsch_approve', '', 'Validation Schedule approved by QA Head. SchID:141', 41, '2025-10-11 11:46:37', 7),
(67850, 'tran_logout', '', 'User qa_head_one has logged out.', 41, '2025-10-11 11:46:42', 7),
(67851, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 11:46:51', 7),
(67852, 'tran_session_destroy', '', 'User engg_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-11 11:47:55', 7),
(67853, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 11:48:28', 7),
(67854, 'tran_session_destroy', '', 'User engg_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-11 11:51:05', 7),
(67855, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 12:00:11', 7),
(67856, 'add_remarks_success', 'approver_remarks', 'Remarks added by user engg_user_one (Remarks ID: 24701) - Authentication completed successfully.', 42, '2025-10-11 12:02:07', 7),
(67857, 'tran_valbgn', '', 'Validation begin. WorkflowID:V-1-7-1760163302-6M', 42, '2025-10-11 12:02:29', 7),
(67858, 'tran_logout', '', 'User engg_user_one has logged out.', 42, '2025-10-11 12:06:46', 7),
(67859, 'tran_login_ext_emp', '', 'Vendor employee vendor_user_one logged into the system.', 74, '2025-10-11 12:06:58', 0),
(67860, 'tran_logout', '', 'User vendor_user_one has logged out.', 74, '2025-10-11 12:07:41', 0),
(67861, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-10-11 12:10:36', 7),
(67862, 'tran_logout', '', 'User it_user_one has logged out.', 2050, '2025-10-11 12:11:29', 7),
(67863, 'tran_login_ext_emp', '', 'Vendor employee vendor_user_one logged into the system.', 74, '2025-10-11 12:11:37', 0),
(67864, 'tran_file_upload', 'tbl_uploads', 'Files uploaded for Test WF ID: T-1-7-2-1760164349 (Val WF ID: V-1-7-1760163302-6M)', 74, '2025-10-11 12:13:00', 0),
(67865, 'tran_session_destroy', '', 'User vendor_user_one session destroyed (Application switching timeout - 30 seconds)', 0, '2025-10-11 12:13:55', 0),
(67866, 'tran_session_destroy', '', 'User Unknown session destroyed (Application switching timeout - 30 seconds)', 0, '2025-10-11 12:13:55', 0),
(67867, 'tran_session_destroy', '', 'User Unknown session destroyed (Screen/lid locked - security logout)', 0, '2025-10-11 12:13:56', 0),
(67868, 'tran_session_destroy', '', 'User Unknown session destroyed (Screen/lid locked - security logout)', 0, '2025-10-11 12:13:56', 0),
(67869, 'security_error', '', 'Unknown user login failed. Entered user name: vendor_user_one From IP: 127.0.0.1', 0, '2025-10-11 12:20:05', NULL),
(67870, 'tran_login_ext_emp', '', 'Vendor employee vendor_user_one logged into the system.', 74, '2025-10-11 12:20:09', 0),
(67871, 'tran_login_ext_emp', '', 'Vendor employee vendor_user_one logged into the system.', 74, '2025-10-11 12:20:12', 0),
(67872, 'tran_session_destroy', '', 'User vendor_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-11 12:21:32', 0),
(67873, 'tran_login_ext_emp', '', 'Vendor employee vendor_user_one logged into the system.', 74, '2025-10-11 12:22:15', 0),
(67874, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test submitted by vendor_user_one. Test WfID:T-1-7-2-1760164349', 74, '2025-10-11 12:22:43', 0),
(67875, 'tran_logout', '', 'User vendor_user_one has logged out.', 74, '2025-10-11 12:23:22', 0),
(67876, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 12:27:44', 7),
(67877, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Raw Data document. Upload ID: 9458, Test WF ID: T-1-7-2-1760164349, View ID: 1760165950331.', 42, '2025-10-11 12:29:10', 7),
(67878, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Master Certificate document. Upload ID: 9458, Test WF ID: T-1-7-2-1760164349, View ID: 1760165959087.', 42, '2025-10-11 12:29:19', 7),
(67879, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Test Certificate document. Upload ID: 9458, Test WF ID: T-1-7-2-1760164349, View ID: 1760165962765.', 42, '2025-10-11 12:29:22', 7),
(67880, 'tran_upload_files_app', 'tbl_uploads', 'Uploaded files approved. Upload ID:9458 Test WF ID:T-1-7-2-1760164349', 42, '2025-10-11 12:29:33', 7),
(67881, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test reviewed by engg_user_one. Test WfID:T-1-7-2-1760164349', 42, '2025-10-11 12:29:52', 7),
(67882, 'tran_session_destroy', '', 'User engg_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-11 12:31:01', 7),
(67883, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 12:31:25', 7),
(67884, 'tran_logout', '', 'User engg_user_one has logged out.', 42, '2025-10-11 12:31:29', 7),
(67885, 'tran_login_int_emp', '', 'User qa_user_one logged into the system.', 46, '2025-10-11 12:31:41', 7),
(67886, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Raw Data document. Upload ID: 9458, Test WF ID: T-1-7-2-1760164349, View ID: 1760166138022.', 46, '2025-10-11 12:32:18', 7),
(67887, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Master Certificate document. Upload ID: 9458, Test WF ID: T-1-7-2-1760164349, View ID: 1760166141384.', 46, '2025-10-11 12:32:21', 7),
(67888, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Test Certificate document. Upload ID: 9458, Test WF ID: T-1-7-2-1760164349, View ID: 1760166144832.', 46, '2025-10-11 12:32:24', 7),
(67889, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test reviewed by qa_user_one. Test WfID:T-1-7-2-1760164349', 46, '2025-10-11 12:32:55', 7),
(67890, 'security_error', '', 'User QA User One automatically logged out due to inactivity.', 0, '2025-10-11 12:34:20', 7),
(67891, 'tran_session_destroy', '', 'User qa_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-11 12:34:20', 7),
(67892, 'tran_login_ext_emp', '', 'Vendor employee vendor_user_one logged into the system.', 74, '2025-10-11 12:35:03', 0),
(67893, 'test_instrument_add', 'test_instruments', 'Added instrument Air Capture Hood (INs234) to test workflow T-1-7-1-1760164349', 74, '2025-10-11 12:36:07', 0),
(67894, 'test_instrument_add', 'test_instruments', 'Added instrument Particle Counter (INST003) to test workflow T-1-7-1-1760164349', 74, '2025-10-11 12:36:19', 0),
(67895, 'data_entry_mode_selection', 'tbl_test_schedules_tracking', 'Data entry mode selected as ONLINE for Validation Workflow ID: V-1-7-1760163302-6M, Test Workflow ID: T-1-7-1-1760164349', 74, '2025-10-11 12:37:31', 0),
(67896, 'test_specific_data_version', 'test_specific_data', 'Created new version of test-specific data for Acph_filter_1 section, Test Workflow ID: T-1-7-1-1760164349, Filter ID: 1 (Record ID: 39)', 74, '2025-10-11 12:41:57', 7),
(67897, 'test_specific_data_version', 'test_specific_data', 'Created new version of test-specific data for Acph_filter_2 section, Test Workflow ID: T-1-7-1-1760164349, Filter ID: 2 (Record ID: 40)', 74, '2025-10-11 12:42:26', 7),
(67898, 'test_data_finalization', 'tbl_test_finalisation_details', 'Test data finalized for Test: ACPH, Equipment: AHU-01, Validation WF: V-1-7-1760163302-6M, Test WF: T-1-7-1-1760164349. Mode: ONLINE (PDFs generated)', 74, '2025-10-11 12:42:57', 0),
(67899, 'tran_file_view', 'tbl_uploads', 'User Vendor User One I viewed Raw Data document. Upload ID: 9459, Test WF ID: T-1-7-1-1760164349, View ID: 1760166794270.', 74, '2025-10-11 12:43:14', 0),
(67900, 'tran_file_view', 'tbl_uploads', 'User Vendor User One I viewed Test Certificate document. Upload ID: 9459, Test WF ID: T-1-7-1-1760164349, View ID: 1760166832449.', 74, '2025-10-11 12:43:52', 0),
(67901, 'tran_file_view', 'tbl_uploads', 'User Vendor User One I viewed Master Certificate document. Upload ID: 9460, Test WF ID: T-1-7-1-1760164349, View ID: 1760166850992.', 74, '2025-10-11 12:44:11', 0),
(67902, 'tran_file_view', 'tbl_uploads', 'User Vendor User One I viewed Raw Data document. Upload ID: 9459, Test WF ID: T-1-7-1-1760164349, View ID: 1760166862803.', 74, '2025-10-11 12:44:22', 0),
(67903, 'tran_logout', '', 'User vendor_user_one has logged out.', 74, '2025-10-11 12:45:25', 0),
(67904, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 12:45:34', 7),
(67905, 'tran_logout', '', 'User engg_user_one has logged out.', 42, '2025-10-11 12:45:40', 7),
(67906, 'tran_login_ext_emp', '', 'Vendor employee vendor_user_one logged into the system.', 74, '2025-10-11 12:45:47', 0),
(67907, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test submitted by vendor_user_one. Test WfID:T-1-7-1-1760164349', 74, '2025-10-11 12:46:08', 0),
(67908, 'tran_logout', '', 'User vendor_user_one has logged out.', 74, '2025-10-11 12:46:12', 0),
(67909, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 12:46:17', 7),
(67910, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Raw Data document. Upload ID: 9459, Test WF ID: T-1-7-1-1760164349, View ID: 1760167011758.', 42, '2025-10-11 12:46:51', 7),
(67911, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Test Certificate document. Upload ID: 9459, Test WF ID: T-1-7-1-1760164349, View ID: 1760167016567.', 42, '2025-10-11 12:46:56', 7),
(67912, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Master Certificate document. Upload ID: 9460, Test WF ID: T-1-7-1-1760164349, View ID: 1760167020799.', 42, '2025-10-11 12:47:00', 7),
(67913, 'acph_pdf_regeneration_witness', 'tbl_test_schedules_tracking', 'ACPH PDFs regenerated with witness details for test_wf_id: T-1-7-1-1760164349. Document types: raw_data, test_certificate. Witness: Engg User One', 42, '2025-10-11 12:47:07', 7),
(67914, 'tran_upload_files_app', 'tbl_uploads', 'Uploaded files approved. Upload ID:9459 Test WF ID:T-1-7-1-1760164349', 42, '2025-10-11 12:47:07', 7),
(67915, 'tran_upload_files_app', 'tbl_uploads', 'Uploaded files approved. Upload ID:9460 Test WF ID:T-1-7-1-1760164349', 42, '2025-10-11 12:47:12', 7),
(67916, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test reviewed by engg_user_one. Test WfID:T-1-7-1-1760164349', 42, '2025-10-11 12:47:31', 7),
(67917, 'tran_logout', '', 'User engg_user_one has logged out.', 42, '2025-10-11 12:47:56', 7),
(67918, 'tran_login_int_emp', '', 'User qa_user_one logged into the system.', 46, '2025-10-11 12:48:03', 7),
(67919, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Raw Data document. Upload ID: 9459, Test WF ID: T-1-7-1-1760164349, View ID: 1760167096090.', 46, '2025-10-11 12:48:16', 7),
(67920, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Test Certificate document. Upload ID: 9459, Test WF ID: T-1-7-1-1760164349, View ID: 1760167157844.', 46, '2025-10-11 12:49:17', 7),
(67921, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Master Certificate document. Upload ID: 9460, Test WF ID: T-1-7-1-1760164349, View ID: 1760167165506.', 46, '2025-10-11 12:49:25', 7),
(67922, 'qa_approval_pdf_regeneration', 'tbl_uploads', 'QA approval PDFs regenerated for test_wf_id: T-1-7-1-1760164349. 1 of 1 files regenerated successfully. QA: QA User One', 46, '2025-10-11 12:49:31', 7),
(67923, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test reviewed by qa_user_one. Test WfID:T-1-7-1-1760164349', 46, '2025-10-11 12:49:36', 7),
(67924, 'security_error', '', 'User QA User One automatically logged out due to inactivity.', 0, '2025-10-11 12:51:18', 7),
(67925, 'tran_session_destroy', '', 'User qa_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-11 12:51:18', 7),
(67926, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 12:53:29', 7),
(67927, 'security_error', '', 'User Engg User One automatically logged out due to inactivity.', 0, '2025-10-11 12:54:29', 7),
(67928, 'tran_session_destroy', '', 'User engg_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-11 12:54:29', 7),
(67929, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 12:57:52', 7),
(67930, 'tran_ireview_approve', 'tbl_test_schedules_tracking', 'Internal test reviewed. UserID:42 Test WfID:T-1-7-9-1760164349', 42, '2025-10-11 12:58:41', 7),
(67931, 'tran_logout', '', 'User engg_user_one has logged out.', 42, '2025-10-11 12:59:44', 7),
(67932, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-10-11 12:59:51', 7),
(67933, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-10-11 13:03:07', 7),
(67934, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-11 13:03:07', 7),
(67935, 'security_error', '', 'CSRF token validation failed for user: unknown From IP: 127.0.0.1', 0, '2025-10-11 17:22:25', NULL),
(67936, 'tran_login_ext_emp', '', 'Vendor employee vendor_user_one logged into the system.', 74, '2025-10-11 17:22:41', 0),
(67937, 'tran_file_upload', 'tbl_uploads', 'Files uploaded for Test WF ID: T-1-7-3-1760164349 (Val WF ID: V-1-7-1760163302-6M)', 74, '2025-10-11 17:23:25', 0),
(67938, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test submitted by vendor_user_one. Test WfID:T-1-7-3-1760164349', 74, '2025-10-11 17:23:44', 0),
(67939, 'tran_file_upload', 'tbl_uploads', 'Files uploaded for Test WF ID: T-1-7-6-1760164349 (Val WF ID: V-1-7-1760163302-6M)', 74, '2025-10-11 17:24:17', 0),
(67940, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test submitted by vendor_user_one. Test WfID:T-1-7-6-1760164349', 74, '2025-10-11 17:24:29', 0),
(67941, 'tran_logout', '', 'User vendor_user_one has logged out.', 74, '2025-10-11 17:24:33', 0),
(67942, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 17:24:48', 7),
(67943, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Raw Data document. Upload ID: 9461, Test WF ID: T-1-7-3-1760164349, View ID: 1760183706717.', 42, '2025-10-11 17:25:06', 7),
(67944, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Master Certificate document. Upload ID: 9461, Test WF ID: T-1-7-3-1760164349, View ID: 1760183711613.', 42, '2025-10-11 17:25:11', 7),
(67945, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Test Certificate document. Upload ID: 9461, Test WF ID: T-1-7-3-1760164349, View ID: 1760183717474.', 42, '2025-10-11 17:25:17', 7),
(67946, 'tran_upload_files_app', 'tbl_uploads', 'Uploaded files approved. Upload ID:9461 Test WF ID:T-1-7-3-1760164349', 42, '2025-10-11 17:25:24', 7),
(67947, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test reviewed by engg_user_one. Test WfID:T-1-7-3-1760164349', 42, '2025-10-11 17:25:34', 7),
(67948, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Raw Data document. Upload ID: 9462, Test WF ID: T-1-7-6-1760164349, View ID: 1760183747415.', 42, '2025-10-11 17:25:47', 7),
(67949, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Master Certificate document. Upload ID: 9462, Test WF ID: T-1-7-6-1760164349, View ID: 1760183751547.', 42, '2025-10-11 17:25:51', 7),
(67950, 'tran_file_view', 'tbl_uploads', 'User Engg User One viewed Test Certificate document. Upload ID: 9462, Test WF ID: T-1-7-6-1760164349, View ID: 1760183755424.', 42, '2025-10-11 17:25:55', 7),
(67951, 'tran_upload_files_app', 'tbl_uploads', 'Uploaded files approved. Upload ID:9462 Test WF ID:T-1-7-6-1760164349', 42, '2025-10-11 17:26:01', 7),
(67952, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test reviewed by engg_user_one. Test WfID:T-1-7-6-1760164349', 42, '2025-10-11 17:26:13', 7),
(67953, 'tran_logout', '', 'User engg_user_one has logged out.', 42, '2025-10-11 17:26:19', 7),
(67954, 'tran_login_int_emp', '', 'User qa_user_one logged into the system.', 46, '2025-10-11 17:26:29', 7),
(67955, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Raw Data document. Upload ID: 9461, Test WF ID: T-1-7-3-1760164349, View ID: 1760183803548.', 46, '2025-10-11 17:26:43', 7),
(67956, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Master Certificate document. Upload ID: 9461, Test WF ID: T-1-7-3-1760164349, View ID: 1760183809324.', 46, '2025-10-11 17:26:49', 7),
(67957, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Test Certificate document. Upload ID: 9461, Test WF ID: T-1-7-3-1760164349, View ID: 1760183813457.', 46, '2025-10-11 17:26:53', 7),
(67958, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test reviewed by qa_user_one. Test WfID:T-1-7-3-1760164349', 46, '2025-10-11 17:27:08', 7),
(67959, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Raw Data document. Upload ID: 9462, Test WF ID: T-1-7-6-1760164349, View ID: 1760183840201.', 46, '2025-10-11 17:27:20', 7),
(67960, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Master Certificate document. Upload ID: 9462, Test WF ID: T-1-7-6-1760164349, View ID: 1760183844629.', 46, '2025-10-11 17:27:24', 7),
(67961, 'tran_file_view', 'tbl_uploads', 'User QA User One viewed Test Certificate document. Upload ID: 9462, Test WF ID: T-1-7-6-1760164349, View ID: 1760183847911.', 46, '2025-10-11 17:27:27', 7),
(67962, 'tran_ereview_approve', 'tbl_test_schedules_tracking', 'External test reviewed by qa_user_one. Test WfID:T-1-7-6-1760164349', 46, '2025-10-11 17:27:43', 7),
(67963, 'tran_logout', '', 'User qa_user_one has logged out.', 46, '2025-10-11 17:28:29', 7),
(67964, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-11 17:28:42', 7),
(67965, 'add_remarks_success', 'approver_remarks', 'Remarks added by user engg_user_one (Remarks ID: 24715) (Val WF ID: V-1-7-1760163302-6M)', 42, '2025-10-11 17:31:08', 7),
(67966, 'tran_submitted_approval', 'validation_reports', 'Validation study submitted for team approval. UserID:42 WfID:V-1-7-1760163302-6M', 42, '2025-10-11 17:31:10', 7),
(67967, 'add_remarks_success', 'approver_remarks', 'Remarks added by user engg_user_one (Remarks ID: 24716) (Val WF ID: V-1-7-1760163302-6M)', 42, '2025-10-11 17:31:52', 7),
(67968, 'tran_teamapp_eng', 'tbl_val_wf_approval_tracking_details', 'Level1 Engineering approved. UserID:42 WfID:V-1-7-1760163302-6M', 42, '2025-10-11 17:31:54', 7),
(67969, 'tran_logout', '', 'User engg_user_one has logged out.', 42, '2025-10-11 17:32:04', 7),
(67970, 'tran_login_int_emp', '', 'User unit_head_one logged into the system.', 54, '2025-10-11 17:32:13', 7),
(67971, 'add_remarks_success', 'approver_remarks', 'Remarks added by user unit_head_one (Remarks ID: 24717) (Val WF ID: V-1-7-1760163302-6M)', 54, '2025-10-11 17:32:56', 7),
(67972, 'tran_level2app_uh', 'tbl_val_wf_approval_tracking_details', 'Level2 Unit Head approved. UserID:54 WfID:V-1-7-1760163302-6M', 54, '2025-10-11 17:32:57', 7),
(67973, 'tran_logout', '', 'User unit_head_one has logged out.', 54, '2025-10-11 17:33:01', 7),
(67974, 'tran_login_int_emp', '', 'User qa_head_one logged into the system.', 41, '2025-10-11 17:33:12', 7),
(67975, 'add_remarks_success', 'approver_remarks', 'Remarks added by user qa_head_one (Remarks ID: 24718) (Val WF ID: V-1-7-1760163302-6M)', 41, '2025-10-11 17:33:35', 7),
(67976, 'tran_level3app_qh', 'tbl_val_wf_approval_tracking_details', 'Level3 approved. UserID:41 WfID:V-1-7-1760163302-6M', 41, '2025-10-11 17:33:37', 7),
(67977, 'tran_view_schedule', '', 'User QA Head One viewed schedule PDF: uploads/protocol-report-V-1-7-1760163302-6M.pdf', 41, '2025-10-11 17:34:01', 7),
(67978, 'security_error', '', 'User QA Head One automatically logged out due to inactivity.', 0, '2025-10-11 17:35:53', 7),
(67979, 'tran_session_destroy', '', 'User qa_head_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-11 17:35:53', 7),
(67980, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-19 09:14:59', 7),
(67981, 'tran_session_destroy', '', 'User engg_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-19 09:24:19', 7),
(67982, 'security_error', '', 'CSRF token validation failed for user: unknown From IP: 127.0.0.1', 0, '2025-10-19 12:01:20', NULL),
(67983, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-19 12:01:21', 7),
(67984, 'security_error', '', 'User Engg User One automatically logged out due to inactivity.', 0, '2025-10-19 12:02:25', 7),
(67985, 'tran_session_destroy', '', 'User engg_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-19 12:02:25', 7),
(67986, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-10-19 12:03:06', 7),
(67987, 'security_error', '', 'User Engg User One automatically logged out due to inactivity.', 0, '2025-10-19 12:04:06', 7),
(67988, 'tran_session_destroy', '', 'User engg_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-19 12:04:06', 7),
(67989, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-10-19 12:07:24', 7),
(67990, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-10-19 12:10:05', 7),
(67991, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-19 12:10:05', 7),
(67992, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-10-19 12:10:21', 7),
(67993, 'tran_view_schedule', '', 'User IT User One viewed schedule PDF: uploads/schedule-report-7-141.pdf', 2050, '2025-10-19 12:16:39', 7),
(67994, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-10-19 12:20:07', 7),
(67995, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-10-19 12:20:07', 7),
(67996, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-11-14 06:34:21', 7),
(67997, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-11-14 06:37:00', 7),
(67998, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-11-14 06:37:00', 7),
(67999, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-11-14 06:42:07', 7),
(68000, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-11-14 06:43:18', 7),
(68001, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-11-14 06:43:26', 7),
(68002, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-11-14 06:44:53', 7),
(68003, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-11-14 06:44:53', 7),
(68004, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-11-14 06:45:29', 7),
(68005, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-11-14 06:46:48', 7),
(68006, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-11-14 06:46:48', 7),
(68007, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-11-14 06:50:32', 7),
(68008, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-11-14 06:52:10', 7),
(68009, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-11-14 06:52:10', 7),
(68010, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-11-14 06:52:47', 7),
(68011, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-11-14 06:54:59', 7),
(68012, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-11-14 06:54:59', 7),
(68013, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-11-14 06:55:46', 7),
(68014, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-11-14 06:56:54', 7),
(68015, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-11-14 06:56:54', 7),
(68016, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-12-14 18:29:25', 7),
(68017, 'security_error', '', 'User Engg User One automatically logged out due to inactivity.', 0, '2025-12-14 18:34:27', 7),
(68018, 'tran_session_destroy', '', 'User engg_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-14 18:34:27', 7),
(68019, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-12-14 18:37:13', 7),
(68020, 'tran_session_destroy', '', 'User engg_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-14 18:38:30', 7),
(68021, 'tran_login_int_emp', '', 'User engg_user_one logged into the system.', 42, '2025-12-14 18:38:38', 7),
(68022, 'tran_logout', '', 'User engg_user_one has logged out.', 42, '2025-12-14 18:39:15', 7),
(68023, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-12-14 18:39:22', 7),
(68024, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-14 18:41:35', 7),
(68025, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-12-14 18:42:31', 7),
(68026, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-12-14 18:44:21', 7),
(68027, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-14 18:44:21', 7),
(68028, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-12-14 19:07:55', 7),
(68029, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-12-14 19:10:16', 7),
(68030, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-14 19:10:16', 7),
(68031, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-12-14 19:10:45', 7),
(68032, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-14 19:11:55', 7),
(68033, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-12-14 19:12:31', 7),
(68034, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-12-14 19:13:40', 7),
(68035, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-14 19:13:40', 7),
(68036, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-12-14 19:15:14', 7),
(68037, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-14 19:17:39', 7),
(68038, 'security_error', '', 'CSRF token validation failed for user: unknown From IP: 127.0.0.1', 0, '2025-12-15 00:19:49', NULL),
(68039, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-12-15 00:19:50', 7),
(68040, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-12-15 00:26:33', 7),
(68041, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-15 00:26:33', 7),
(68042, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-12-15 00:32:19', 7),
(68043, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-12-15 00:36:47', 7),
(68044, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-15 00:36:47', 7),
(68045, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-12-15 00:40:50', 7),
(68046, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-12-15 00:42:56', 7),
(68047, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-15 00:42:56', 7),
(68048, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2025-12-15 00:44:56', 7),
(68049, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2025-12-15 00:47:43', 7),
(68050, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2025-12-15 00:47:43', 7),
(68051, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2026-01-04 19:00:44', 7),
(68052, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2026-01-04 19:02:07', 7),
(68053, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2026-01-04 19:02:26', 7),
(68054, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2026-01-04 19:03:55', 7),
(68055, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2026-01-04 19:03:55', 7),
(68056, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2026-01-04 19:26:03', 7),
(68057, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2026-01-04 19:27:12', 7),
(68058, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2026-01-04 19:27:12', 7),
(68059, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2026-01-04 19:29:40', 7),
(68060, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2026-01-04 19:30:44', 7),
(68061, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2026-01-04 19:30:44', 7),
(68062, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2026-01-04 19:30:59', 7),
(68063, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2026-01-04 19:32:11', 7),
(68064, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2026-01-04 19:32:11', 7),
(68065, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2026-01-04 19:34:51', 7),
(68066, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2026-01-04 19:36:47', 7),
(68067, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2026-01-04 19:40:18', 7),
(68068, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2026-01-04 19:41:23', 7),
(68069, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2026-01-04 19:42:32', 7),
(68070, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2026-01-04 19:43:47', 7),
(68071, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2026-01-04 19:43:47', 7),
(68072, 'tran_login_int_emp', '', 'User it_user_one logged into the system.', 2050, '2026-01-04 19:51:19', 7),
(68073, 'security_error', '', 'User IT User One automatically logged out due to inactivity.', 0, '2026-01-04 19:52:30', 7),
(68074, 'tran_session_destroy', '', 'User it_user_one session destroyed (Compliance lockout - 1 minute of inactivity)', 0, '2026-01-04 19:52:30', 7);

-- --------------------------------------------------------

--
-- Table structure for table `raw_data_templates`
--

CREATE TABLE `raw_data_templates` (
  `id` int NOT NULL,
  `test_id` int NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `effective_date` date NOT NULL,
  `effective_till_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `download_count` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `raw_data_templates`
--

INSERT INTO `raw_data_templates` (`id`, `test_id`, `file_path`, `effective_date`, `effective_till_date`, `is_active`, `created_by`, `created_at`, `download_count`) VALUES
(2, 6, '/opt/homebrew/var/www/provalnxt/public/uploads/templates/test_6_template_1754971127.pdf', '2025-08-12', NULL, 0, 42, '2025-09-07 01:56:57', 0),
(3, 6, '/opt/homebrew/var/www/provalnxt/public/uploads/templates/test_6_template_1756107479.pdf', '2025-08-25', NULL, 0, 42, '2025-09-07 01:56:57', 0),
(4, 6, '/opt/homebrew/var/www/provalnxt/public/uploads/templates/test_6_template_1756107651.pdf', '2025-08-25', NULL, 0, 42, '2025-09-07 01:56:57', 0),
(5, 6, '/opt/homebrew/var/www/provalnxt/public/uploads/templates/test_6_template_1756108227.pdf', '2025-08-25', NULL, 0, 42, '2025-09-07 01:56:57', 0),
(6, 6, '/opt/homebrew/var/www/provalnxt/public/uploads/templates/test_6_template_1756108572.pdf', '2025-08-25', NULL, 0, 42, '2025-09-07 01:56:57', 0),
(7, 6, '/opt/homebrew/var/www/provalnxt/public/uploads/templates/test_6_template_1756109344.pdf', '2025-08-25', NULL, 0, 42, '2025-09-07 01:56:57', 0),
(8, 6, '/opt/homebrew/var/www/provalnxt/public/uploads/templates/test_6_template_1756109844.pdf', '2025-08-25', NULL, 0, 42, '2025-09-07 01:56:57', 0),
(9, 6, '/opt/homebrew/var/www/provalnxt/public/uploads/templates/test_6_template_1756110155.pdf', '2025-08-25', NULL, 0, 42, '2025-09-07 01:56:57', 0),
(10, 6, '/opt/homebrew/var/www/provalnxt/public/uploads/templates/test_6_template_1756112748.pdf', '2025-08-25', NULL, 0, 42, '2025-09-07 01:56:57', 0),
(11, 6, '/opt/homebrew/var/www/provalnxt/public/uploads/templates/test_6_template_1756114969.pdf', '2025-08-25', NULL, 0, 42, '2025-09-07 01:56:57', 0),
(12, 6, '/opt/homebrew/var/www/provalnxt/public/uploads/templates/test_6_template_1756126480.pdf', '2025-08-25', NULL, 1, 42, '2025-09-07 01:56:57', 0);

-- --------------------------------------------------------

--
-- Table structure for table `room_locations`
--

CREATE TABLE `room_locations` (
  `room_loc_id` int NOT NULL COMMENT 'Unique identifier for room location',
  `room_loc_name` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the room or location',
  `room_volume` decimal(10,2) NOT NULL COMMENT 'Volume of the room in cubic feet',
  `creation_datetime` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time when record was created',
  `last_modification_datetime` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date and time when record was last modified'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master table for room locations and their volumes';

--
-- Dumping data for table `room_locations`
--

INSERT INTO `room_locations` (`room_loc_id`, `room_loc_name`, `room_volume`, `creation_datetime`, `last_modification_datetime`) VALUES
(1, 'Vial Filling Lyo Loading and Unloading Area', 4354.30, '2025-09-04 22:15:38', '2025-09-04 22:15:38'),
(2, 'Location 1', 0.00, '2025-09-04 22:15:38', '2025-09-04 22:16:46'),
(3, 'Location 2', 0.00, '2025-09-04 22:15:38', '2025-09-04 22:16:46'),
(4, 'Location 3', 0.00, '2025-09-04 22:15:38', '2025-09-04 22:16:59');

-- --------------------------------------------------------

--
-- Table structure for table `routine_tests_schedules`
--

CREATE TABLE `routine_tests_schedules` (
  `routine_test_schedule_id` int NOT NULL,
  `routine_test_workflow_id` varchar(45) DEFAULT NULL,
  `equipment_id` int DEFAULT NULL,
  `test_id` int DEFAULT NULL,
  `test_frequency` varchar(45) DEFAULT NULL,
  `routine_test_status` varchar(45) DEFAULT NULL,
  `routine_test_created_datetime` datetime DEFAULT NULL,
  `routine_test_last_modification_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scheduled_emails`
--

CREATE TABLE `scheduled_emails` (
  `communication_id` int NOT NULL,
  `set_from_email` varchar(100) DEFAULT NULL,
  `set_from_name` varchar(100) DEFAULT NULL,
  `recipient_name` varchar(200) DEFAULT NULL,
  `recipient_email` varchar(200) DEFAULT NULL,
  `cc_email` varchar(500) DEFAULT NULL,
  `bcc_email` varchar(500) DEFAULT NULL,
  `subject` varchar(500) DEFAULT NULL,
  `body` varchar(5000) DEFAULT NULL,
  `coomunication_type` varchar(100) DEFAULT NULL,
  `creation_datetime` datetime DEFAULT NULL,
  `sent_status` varchar(45) DEFAULT NULL,
  `sent_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_database_migrations`
--

CREATE TABLE `tbl_database_migrations` (
  `migration_id` int NOT NULL,
  `migration_name` varchar(255) NOT NULL,
  `migration_version` varchar(50) NOT NULL,
  `executed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_database_migrations`
--

INSERT INTO `tbl_database_migrations` (`migration_id`, `migration_name`, `migration_version`, `executed_at`) VALUES
(1, '001_create_email_reminder_tables', '1.0', '2025-08-03 08:38:48');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_email_configuration`
--

CREATE TABLE `tbl_email_configuration` (
  `email_configuration_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `event_name` varchar(200) DEFAULT NULL,
  `email_ids_to` varchar(2000) DEFAULT NULL,
  `email_ids_cc` varchar(2000) DEFAULT NULL,
  `email_ids_bcc` varchar(2000) DEFAULT NULL,
  `created_date_time` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_modified_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `email_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Enable/disable emails for this configuration',
  `email_frequency` varchar(50) DEFAULT NULL COMMENT 'Email frequency (daily, weekly, etc.)',
  `last_sent_date` datetime DEFAULT NULL COMMENT 'Last time email was sent',
  `retry_count` int NOT NULL DEFAULT '0' COMMENT 'Number of retry attempts',
  `created_date` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Configuration creation date',
  `updated_date` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update date'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_email_configuration`
--

INSERT INTO `tbl_email_configuration` (`email_configuration_id`, `unit_id`, `event_name`, `email_ids_to`, `email_ids_cc`, `email_ids_bcc`, `created_date_time`, `last_modified_date_time`, `email_enabled`, `email_frequency`, `last_sent_date`, `retry_count`, `created_date`, `updated_date`) VALUES
(1, 8, 'rem_val_not_started_10days_prior', 'pandurang.nayak@cipla.com;prashant.naik@cipla.com;shirish.phadte@cipla.com;ganesh.baviskar@cipla.com', '', '', '2021-09-01 00:41:37', '2021-09-01 00:41:37', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(2, 8, 'rem_val_not_started_30days_prior', 'pandurang.nayak@cipla.com;prashant.naik@cipla.com;shirish.phadte@cipla.com;ganesh.baviskar@cipla.com', '', '', '2021-09-01 00:41:37', '2021-09-01 00:41:37', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(3, 8, 'rem_val_started_30days_after', 'pandurang.nayak@cipla.com;prashant.naik@cipla.com;shirish.phadte@cipla.com;ganesh.baviskar@cipla.com', '', '', '2021-09-01 00:41:37', '2021-09-01 00:41:37', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(4, 8, 'rem_val_started_35days_after', 'pandurang.nayak@cipla.com;prashant.naik@cipla.com;shirish.phadte@cipla.com;ganesh.baviskar@cipla.com', '', '', '2021-09-01 00:41:37', '2021-09-01 00:41:37', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(5, 8, 'rem_val_started_40days_after', 'pandurang.nayak@cipla.com;prashant.naik@cipla.com;shirish.phadte@cipla.com;ganesh.baviskar@cipla.com', '', '', '2021-09-01 00:41:37', '2021-09-01 00:41:37', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(6, 8, 'rem_val_not_started_10days_prior', 'pandurang.nayak@cipla.com;prashant.naik@cipla.com;shirish.phadte@cipla.com;ganesh.baviskar@cipla.com', '', '', '2021-09-01 00:41:37', '2021-09-01 00:41:37', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(7, 8, 'rem_val_not_started_30days_prior', 'pandurang.nayak@cipla.com;prashant.naik@cipla.com;shirish.phadte@cipla.com;ganesh.baviskar@cipla.com', '', '', '2021-09-01 00:41:37', '2021-09-01 00:41:37', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(8, 8, 'rem_val_started_30days_after', 'pandurang.nayak@cipla.com;prashant.naik@cipla.com;shirish.phadte@cipla.com;ganesh.baviskar@cipla.com', '', '', '2021-09-01 00:41:37', '2021-09-01 00:41:37', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(9, 8, 'rem_val_started_35days_after', 'pandurang.nayak@cipla.com;prashant.naik@cipla.com;shirish.phadte@cipla.com;ganesh.baviskar@cipla.com', '', '', '2021-09-01 00:41:37', '2021-09-01 00:41:37', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(10, 8, 'rem_val_started_40days_after', 'pandurang.nayak@cipla.com;prashant.naik@cipla.com;shirish.phadte@cipla.com;ganesh.baviskar@cipla.com', '', '', '2021-09-01 00:41:37', '2021-09-01 00:41:37', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(11, 4, 'rem_val_not_started_10days_prior', 'rahul.dhume2@Cipla.com;Vinay.Gaude@Cipla.com;shikandar.sawakhande@cipla.com;sanjay.n@Cipla.com;cajetan.gracias1@cipla.com;swapnil.hajare@cipla.com;puran.mahto@cipla.com;sachin.kedar@cipla.com;santosh.patil4@cipla.com;naresh.naik@cipla.com;nelija.dias@Cipla.com;prayank.matiman@Cipla.com;shubham.rane@Cipla.com', '', '', '2024-08-19 16:55:39', '0000-00-00 00:00:00', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(12, 4, 'rem_val_not_started_30days_prior', 'rahul.dhume2@Cipla.com;Vinay.Gaude@Cipla.com;shikandar.sawakhande@cipla.com;sanjay.n@Cipla.com;cajetan.gracias1@cipla.com;swapnil.hajare@cipla.com;puran.mahto@cipla.com;sachin.kedar@cipla.com;santosh.patil4@cipla.com;naresh.naik@cipla.com;nelija.dias@Cipla.com;prayank.matiman@Cipla.com;shubham.rane@Cipla.com', '', '', '2024-08-19 16:55:39', '0000-00-00 00:00:00', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(13, 4, 'rem_val_started_30days_after', 'rahul.dhume2@Cipla.com;Vinay.Gaude@Cipla.com;shikandar.sawakhande@cipla.com;sanjay.n@Cipla.com;cajetan.gracias1@cipla.com;swapnil.hajare@cipla.com;puran.mahto@cipla.com;sachin.kedar@cipla.com;santosh.patil4@cipla.com;naresh.naik@cipla.com;nelija.dias@Cipla.com;prayank.matiman@Cipla.com;shubham.rane@Cipla.com', '', '', '2024-08-19 16:55:39', '0000-00-00 00:00:00', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(14, 4, 'rem_val_started_35days_after', 'rahul.dhume2@Cipla.com;Vinay.Gaude@Cipla.com;shikandar.sawakhande@cipla.com;sanjay.n@Cipla.com;cajetan.gracias1@cipla.com;swapnil.hajare@cipla.com;puran.mahto@cipla.com;sachin.kedar@cipla.com;santosh.patil4@cipla.com;naresh.naik@cipla.com;nelija.dias@Cipla.com;prayank.matiman@Cipla.com;shubham.rane@Cipla.com', '', '', '2024-08-19 16:55:39', '0000-00-00 00:00:00', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(15, 4, 'rem_val_started_40days_after', 'rahul.dhume2@Cipla.com;Vinay.Gaude@Cipla.com;shikandar.sawakhande@cipla.com;sanjay.n@Cipla.com;cajetan.gracias1@cipla.com;swapnil.hajare@cipla.com;puran.mahto@cipla.com;sachin.kedar@cipla.com;santosh.patil4@cipla.com;naresh.naik@cipla.com;nelija.dias@Cipla.com;prayank.matiman@Cipla.com;shubham.rane@Cipla.com', '', '', '2024-08-19 16:55:39', '0000-00-00 00:00:00', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(16, 4, 'rem_val_not_started_10days_prior', 'rahul.dhume2@Cipla.com;Vinay.Gaude@Cipla.com;shikandar.sawakhande@cipla.com;sanjay.n@Cipla.com;cajetan.gracias1@cipla.com;swapnil.hajare@cipla.com;puran.mahto@cipla.com;sachin.kedar@cipla.com;santosh.patil4@cipla.com;naresh.naik@cipla.com;nelija.dias@Cipla.com;prayank.matiman@Cipla.com;shubham.rane@Cipla.com', '', '', '2024-08-19 16:55:39', '0000-00-00 00:00:00', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(17, 4, 'rem_val_not_started_30days_prior', 'rahul.dhume2@Cipla.com;Vinay.Gaude@Cipla.com;shikandar.sawakhande@cipla.com;sanjay.n@Cipla.com;cajetan.gracias1@cipla.com;swapnil.hajare@cipla.com;puran.mahto@cipla.com;sachin.kedar@cipla.com;santosh.patil4@cipla.com;naresh.naik@cipla.com;nelija.dias@Cipla.com;prayank.matiman@Cipla.com;shubham.rane@Cipla.com', '', '', '2024-08-19 16:55:39', '0000-00-00 00:00:00', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(18, 4, 'rem_val_started_30days_after', 'rahul.dhume2@Cipla.com;Vinay.Gaude@Cipla.com;shikandar.sawakhande@cipla.com;sanjay.n@Cipla.com;cajetan.gracias1@cipla.com;swapnil.hajare@cipla.com;puran.mahto@cipla.com;sachin.kedar@cipla.com;santosh.patil4@cipla.com;naresh.naik@cipla.com;nelija.dias@Cipla.com;prayank.matiman@Cipla.com;shubham.rane@Cipla.com', '', '', '2024-08-19 16:55:39', '0000-00-00 00:00:00', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(19, 4, 'rem_val_started_35days_after', 'rahul.dhume2@Cipla.com;Vinay.Gaude@Cipla.com;shikandar.sawakhande@cipla.com;sanjay.n@Cipla.com;cajetan.gracias1@cipla.com;swapnil.hajare@cipla.com;puran.mahto@cipla.com;sachin.kedar@cipla.com;santosh.patil4@cipla.com;naresh.naik@cipla.com;nelija.dias@Cipla.com;prayank.matiman@Cipla.com;shubham.rane@Cipla.com', '', '', '2024-08-19 16:55:39', '0000-00-00 00:00:00', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(20, 4, 'rem_val_started_40days_after', 'rahul.dhume2@Cipla.com;Vinay.Gaude@Cipla.com;shikandar.sawakhande@cipla.com;sanjay.n@Cipla.com;cajetan.gracias1@cipla.com;swapnil.hajare@cipla.com;puran.mahto@cipla.com;sachin.kedar@cipla.com;santosh.patil4@cipla.com;naresh.naik@cipla.com;nelija.dias@Cipla.com;prayank.matiman@Cipla.com;shubham.rane@Cipla.com', '', '', '2024-08-19 16:55:39', '0000-00-00 00:00:00', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(21, 7, 'rem_val_not_started_10days_prior', 'kishan.naik@cipla.com;\nsuraj.madival@cipla.com;sabbavarapu.siva@cipla.com;chetan.desale@cipla.com;saahil.rane1@cipla.com', '', NULL, '2024-05-22 17:12:56', '2023-09-30 21:41:47', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(22, 7, 'rem_val_not_started_30days_prior', 'kishan.naik@cipla.com; suraj.madival@cipla.com;sabbavarapu.siva@cipla.com;chetan.desale@cipla.com;saahil.rane1@cipla.com', '', NULL, '2024-05-22 17:12:56', '2023-09-30 21:41:47', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(23, 7, 'rem_val_started_30days_after', 'kishan.naik@cipla.com; suraj.madival@cipla.com;sabbavarapu.siva@cipla.com;chetan.desale@cipla.com;saahil.rane1@cipla.com', 'suraj.aghav@cipla.com;srinibas.mishra@cipla.com', '', '2024-05-22 17:12:56', '2023-09-30 21:41:47', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(24, 7, 'rem_val_started_35days_after', 'kishan.naik@cipla.com; suraj.madival@cipla.com;sabbavarapu.siva@cipla.com;chetan.desale@cipla.com;saahil.rane1@cipla.com', 'suraj.aghav@cipla.com;srinibas.mishra@cipla.com', 'vaibhav.pandit@cipla.com', '2024-05-22 17:12:56', '2023-09-30 21:41:47', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(25, 7, 'rem_val_started_40days_after', 'kishan.naik@cipla.com; suraj.madival@cipla.com;sabbavarapu.siva@cipla.com;chetan.desale@cipla.com;saahil.rane1@cipla.com', 'suraj.aghav@cipla.com;srinibas.mishra@cipla.com', 'vaibhav.pandit@cipla.com', '2024-05-22 17:12:56', '2023-09-30 21:41:47', 1, NULL, NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(26, 8, 'validation_not_started_10_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(27, 9, 'validation_not_started_10_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(28, 72, 'validation_not_started_10_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(29, 8, 'validation_not_started_30_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(30, 9, 'validation_not_started_30_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(31, 72, 'validation_not_started_30_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(32, 8, 'validation_in_progress_30_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(33, 9, 'validation_in_progress_30_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(34, 72, 'validation_in_progress_30_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(35, 8, 'validation_in_progress_35_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(36, 9, 'validation_in_progress_35_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(37, 72, 'validation_in_progress_35_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(38, 8, 'validation_in_progress_38_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(39, 9, 'validation_in_progress_38_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48'),
(40, 72, 'validation_in_progress_38_days', 'admin@company.com', '', '', '2025-08-03 08:38:48', '2025-08-03 08:38:48', 1, 'daily', NULL, 0, '2025-08-03 08:38:48', '2025-08-03 08:38:48');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_email_events`
--

CREATE TABLE `tbl_email_events` (
  `event_id` int NOT NULL,
  `event_name` varchar(100) DEFAULT NULL,
  `event_description` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_email_reminder_job_logs`
--

CREATE TABLE `tbl_email_reminder_job_logs` (
  `job_execution_id` int NOT NULL,
  `job_name` varchar(100) NOT NULL,
  `execution_start_time` datetime NOT NULL,
  `execution_end_time` datetime DEFAULT NULL,
  `status` enum('running','completed','failed','skipped') NOT NULL DEFAULT 'running',
  `final_message` text,
  `emails_sent` int NOT NULL DEFAULT '0',
  `emails_failed` int NOT NULL DEFAULT '0',
  `execution_time_seconds` int NOT NULL DEFAULT '0',
  `additional_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='EmailReminder job execution logs';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_email_reminder_logs`
--

CREATE TABLE `tbl_email_reminder_logs` (
  `email_log_id` int NOT NULL,
  `job_execution_id` int DEFAULT NULL,
  `job_name` varchar(100) NOT NULL,
  `unit_id` int NOT NULL,
  `email_subject` text NOT NULL,
  `email_body_html` longtext NOT NULL,
  `email_body_text` longtext,
  `sender_email` varchar(255) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `sent_datetime` datetime NOT NULL,
  `delivery_status` enum('pending','sent','failed','bounced','delivered') NOT NULL DEFAULT 'pending',
  `smtp_response` text,
  `error_message` text,
  `total_recipients` int NOT NULL DEFAULT '0',
  `successful_sends` int NOT NULL DEFAULT '0',
  `failed_sends` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='EmailReminder email sending logs';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_email_reminder_recipients`
--

CREATE TABLE `tbl_email_reminder_recipients` (
  `recipient_log_id` int NOT NULL,
  `email_log_id` int NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_type` enum('to','cc','bcc') NOT NULL,
  `delivery_status` enum('pending','sent','failed','bounced','delivered','opened','clicked') NOT NULL DEFAULT 'pending',
  `delivery_datetime` datetime NOT NULL,
  `bounce_reason` text,
  `smtp_response` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='EmailReminder individual recipient delivery tracking';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_email_reminder_system_logs`
--

CREATE TABLE `tbl_email_reminder_system_logs` (
  `log_id` int NOT NULL,
  `log_level` enum('ERROR','WARNING','INFO','DEBUG') NOT NULL,
  `log_source` varchar(100) NOT NULL,
  `log_message` text NOT NULL,
  `log_data` json DEFAULT NULL,
  `log_datetime` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='EmailReminder system logs for errors and warnings';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_prod_config`
--

CREATE TABLE `tbl_prod_config` (
  `id` int NOT NULL,
  `prod_version` varchar(45) DEFAULT NULL,
  `client_identifier` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_prod_config`
--

INSERT INTO `tbl_prod_config` (`id`, `prod_version`, `client_identifier`) VALUES
(1, '3.0', 'Cipla Goa AU');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_proposed_routine_test_schedules`
--

CREATE TABLE `tbl_proposed_routine_test_schedules` (
  `proposed_sch_row_id` int NOT NULL,
  `schedule_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `equip_id` int NOT NULL,
  `routine_test_wf_id` varchar(45) NOT NULL,
  `routine_test_wf_planned_start_date` date NOT NULL,
  `routine_test_wf_planned_end_date` date DEFAULT NULL,
  `created_date_time` datetime DEFAULT NULL,
  `last_modified_date_time` datetime DEFAULT NULL,
  `test_id` int DEFAULT NULL,
  `routine_test_wf_status` varchar(45) DEFAULT NULL,
  `routine_test_req_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_proposed_val_schedules`
--

CREATE TABLE `tbl_proposed_val_schedules` (
  `proposed_sch_row_id` int NOT NULL,
  `schedule_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `equip_id` int NOT NULL,
  `val_wf_id` varchar(45) NOT NULL,
  `val_wf_planned_start_date` date NOT NULL,
  `val_wf_planned_end_date` date DEFAULT NULL,
  `val_wf_status` varchar(45) DEFAULT NULL,
  `created_date_time` datetime DEFAULT NULL,
  `last_modified_date_time` datetime DEFAULT NULL,
  `frequency_type` varchar(10) DEFAULT NULL COMMENT 'Validation frequency type (6M, Y, 2Y)',
  `cycle_position` int DEFAULT '0' COMMENT 'Current position in frequency cycle',
  `cycle_count` int DEFAULT '0' COMMENT 'Number of completed frequency cycles'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_report_approvers`
--

CREATE TABLE `tbl_report_approvers` (
  `val_wf_id` varchar(50) NOT NULL,
  `iteration_id` int NOT NULL DEFAULT '1',
  `level1_approver_engg` int DEFAULT NULL,
  `level1_approver_hse` int DEFAULT NULL,
  `level1_approver_qc` int DEFAULT NULL,
  `level1_approver_qa` int DEFAULT NULL,
  `level1_approver_user` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_report_approvers`
--

INSERT INTO `tbl_report_approvers` (`val_wf_id`, `iteration_id`, `level1_approver_engg`, `level1_approver_hse`, `level1_approver_qc`, `level1_approver_qa`, `level1_approver_user`) VALUES
('V-1-7-1760163302-6M', 1, 42, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_routine_tests_requests`
--

CREATE TABLE `tbl_routine_tests_requests` (
  `routine_test_request_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `equipment_id` int NOT NULL,
  `test_id` int NOT NULL,
  `test_frequency` varchar(45) NOT NULL,
  `adhoc_frequency` enum('scheduled','adhoc') NOT NULL DEFAULT 'scheduled' COMMENT 'Indicates if routine test is scheduled (regular) or adhoc (unplanned)',
  `test_planned_start_date` date NOT NULL,
  `routine_test_status` tinyint DEFAULT NULL,
  `last_modified_date_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creation_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `routine_test_requested_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_routine_tests_requests`
--

INSERT INTO `tbl_routine_tests_requests` (`routine_test_request_id`, `unit_id`, `equipment_id`, `test_id`, `test_frequency`, `adhoc_frequency`, `test_planned_start_date`, `routine_test_status`, `last_modified_date_time`, `creation_date_time`, `routine_test_requested_by`) VALUES
(1692, 7, 1, 6, 'Q', 'scheduled', '2024-11-21', 1, '2025-08-26 19:06:23', '2025-06-05 08:04:06', NULL),
(1693, 7, 2, 6, 'H', 'scheduled', '2025-01-14', 1, '2025-08-10 04:34:47', '2025-06-05 08:04:06', NULL),
(1694, 7, 3, 6, 'Y', 'scheduled', '2024-03-16', 1, '2025-08-11 04:14:56', '2025-06-05 08:04:06', NULL),
(10002, 7, 3, 1, 'ADHOC', 'adhoc', '2025-08-29', 0, '2025-08-26 19:07:45', '2025-08-27 00:37:21', 42);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_routine_test_schedules`
--

CREATE TABLE `tbl_routine_test_schedules` (
  `routine_test_sch_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `equip_id` int NOT NULL,
  `test_id` int NOT NULL,
  `routine_test_wf_id` varchar(45) NOT NULL,
  `routine_test_wf_planned_start_date` date NOT NULL,
  `routine_test_wf_planned_end_date` date DEFAULT NULL,
  `routine_test_wf_status` varchar(45) DEFAULT 'Active',
  `created_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_modified_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `routine_test_req_id` int DEFAULT NULL,
  `is_adhoc` char(4) DEFAULT 'N',
  `requested_by_user_id` int DEFAULT NULL,
  `parent_routine_test_wf_id` varchar(45) DEFAULT NULL COMMENT 'Parent routine test for auto-created tests',
  `auto_created` char(1) DEFAULT 'N' COMMENT 'Flag indicating auto-created routine test',
  `actual_execution_date` date DEFAULT NULL COMMENT 'Actual test execution date for reference',
  `test_origin` enum('system_original','system_auto_created','user_manual_adhoc') DEFAULT NULL COMMENT 'Origin of test: NULL/blank=system_original, system_auto_created=created by auto-scheduling, user_manual_adhoc=manually added by user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Triggers `tbl_routine_test_schedules`
--
DELIMITER $$
CREATE TRIGGER `tr_routine_schedules_update_timestamp` BEFORE UPDATE ON `tbl_routine_test_schedules` FOR EACH ROW BEGIN
    SET NEW.last_modified_date_time = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_routine_test_schedule_changes`
--

CREATE TABLE `tbl_routine_test_schedule_changes` (
  `change_id` int NOT NULL,
  `affected_routine_test_wf_id` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'The routine test that was modified or created',
  `equipment_id` int NOT NULL COMMENT 'Equipment that this schedule change affects',
  `test_id` int NOT NULL COMMENT 'Type of test being scheduled',
  `unit_id` int DEFAULT NULL COMMENT 'Unit where the equipment is located',
  `original_planned_date` date DEFAULT NULL COMMENT 'Original scheduled date (NULL for new creations)',
  `new_planned_date` date NOT NULL COMMENT 'New or created scheduled date',
  `days_shifted` int GENERATED ALWAYS AS ((case when (`original_planned_date` is not null) then (to_days(`new_planned_date`) - to_days(`original_planned_date`)) else NULL end)) STORED COMMENT 'Days shifted (positive=later, negative=earlier, NULL for creations)',
  `triggering_execution_date` date NOT NULL COMMENT 'Date when the completed test was actually executed',
  `triggering_routine_test_wf_id` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'The completed test that triggered this change',
  `frequency` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Test frequency (Q, HY, Y, etc.)',
  `schedule_year` int NOT NULL COMMENT 'Year of the schedule being modified',
  `change_type` enum('schedule_update','schedule_creation','manual_adjustment','system_correction') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'schedule_update' COMMENT 'Type of change being made',
  `change_reason` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Detailed reason for the schedule change',
  `execution_variance_days` int GENERATED ALWAYS AS ((case when (`original_planned_date` is not null) then (to_days(`triggering_execution_date`) - to_days(`original_planned_date`)) else (to_days(`triggering_execution_date`) - to_days((`triggering_execution_date` - interval (case `frequency` when _utf8mb4'Q' then 3 when _utf8mb4'HY' then 6 when _utf8mb4'Y' then 12 else 12 end) month))) end)) STORED COMMENT 'How early/late the triggering execution was',
  `frequency_compliance_maintained` tinyint(1) DEFAULT '1' COMMENT 'Whether proper frequency interval is maintained',
  `expected_interval_days` int GENERATED ALWAYS AS ((case `frequency` when _utf8mb4'Q' then 91 when _utf8mb4'HY' then 182 when _utf8mb4'Y' then 365 when _utf8mb4'2Y' then 730 else 365 end)) STORED COMMENT 'Expected days between tests for this frequency',
  `change_timestamp` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'When this change was made',
  `changed_by` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'SYSTEM_AUTO_SCHEDULE' COMMENT 'Who or what made this change',
  `system_version` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ProVal4_FreqCompliant_v2' COMMENT 'System version that made the change',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Additional context or details about the change',
  `affected_test_origin` enum('system_original','system_auto_created','user_manual_adhoc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Origin classification of the affected test being modified/created',
  `triggering_test_origin` enum('system_original','system_auto_created','user_manual_adhoc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Origin classification of the test that triggered this change'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comprehensive audit trail for all routine test schedule modifications and creations';

--
-- Dumping data for table `tbl_routine_test_schedule_changes`
--

INSERT INTO `tbl_routine_test_schedule_changes` (`change_id`, `affected_routine_test_wf_id`, `equipment_id`, `test_id`, `unit_id`, `original_planned_date`, `new_planned_date`, `triggering_execution_date`, `triggering_routine_test_wf_id`, `frequency`, `schedule_year`, `change_type`, `change_reason`, `frequency_compliance_maintained`, `change_timestamp`, `changed_by`, `system_version`, `notes`, `affected_test_origin`, `triggering_test_origin`) VALUES
(1, 'R-AHU01-2026-Q2', 72, 1, 1, '2026-05-21', '2025-11-09', '2025-08-10', 'R-AHU01-2026-Q1', 'Q', 2026, 'schedule_update', 'Rescheduled due to frequency compliance - maintains consistent Q intervals', 1, '2025-08-10 02:42:32', 'SYSTEM_AUTO_SCHEDULE', 'ProVal4_FreqCompliant_v2', 'Updated from 2026-05-21 to 2025-11-09 based on 2025-08-10 execution', NULL, NULL),
(2, 'R1736408523-73', 73, 1, 1, NULL, '2026-08-09', '2025-08-10', 'R-AHU03-2026-A1', 'Y', 2026, 'schedule_creation', 'New routine test created due to frequency compliance - maintains consistent Y intervals within schedule year', 1, '2025-08-10 02:42:32', 'SYSTEM_AUTO_SCHEDULE', 'ProVal4_FreqCompliant_v2', 'Created new test scheduled for 2026-08-09 based on 2025-08-10 execution. No existing future schedule found.', NULL, NULL),
(3, 'R-AHU01-2026-Q3', 72, 1, 1, '2026-08-21', '2026-02-06', '2025-11-08', 'R-AHU01-2026-Q2', 'Q', 2026, 'schedule_update', 'Rescheduled due to frequency compliance - maintains consistent Q intervals', 1, '2025-08-10 02:42:32', 'SYSTEM_AUTO_SCHEDULE', 'ProVal4_FreqCompliant_v2', 'Updated from 2026-08-21 to 2026-02-06 based on 2025-11-08 execution (1 day early)', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_routine_test_wf_schedule_requests`
--

CREATE TABLE `tbl_routine_test_wf_schedule_requests` (
  `schedule_id` int NOT NULL,
  `schedule_year` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `schedule_generation_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `schedule_request_status` varchar(45) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_routine_test_wf_tracking_details`
--

CREATE TABLE `tbl_routine_test_wf_tracking_details` (
  `routine_test_wf_tracking_id` int NOT NULL,
  `routine_test_wf_id` varchar(45) NOT NULL,
  `equipment_id` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `actual_wf_start_datetime` datetime NOT NULL,
  `actual_wf_end_datetime` datetime DEFAULT NULL,
  `wf_initiated_by_user_id` int DEFAULT NULL,
  `status` varchar(45) DEFAULT 'Active',
  `created_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_modified_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `routine_test_wf_current_stage` varchar(45) DEFAULT NULL,
  `stage_assigned_datetime` datetime DEFAULT NULL,
  `deviation_remark` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_test_finalisation_details`
--

CREATE TABLE `tbl_test_finalisation_details` (
  `test_id` int NOT NULL,
  `test_wf_id` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `test_finalised_on` datetime DEFAULT CURRENT_TIMESTAMP,
  `test_finalised_by` int DEFAULT NULL,
  `test_witnessed_on` datetime DEFAULT CURRENT_TIMESTAMP,
  `witness` int DEFAULT NULL,
  `witness_action` enum('approve','reject') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `creation_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Table to track test finalisation and witness approval/rejection details with status tracking';

--
-- Dumping data for table `tbl_test_finalisation_details`
--

INSERT INTO `tbl_test_finalisation_details` (`test_id`, `test_wf_id`, `test_finalised_on`, `test_finalised_by`, `test_witnessed_on`, `witness`, `witness_action`, `status`, `creation_datetime`) VALUES
(14, 'T-1-7-1-1756793223', '2025-09-08 21:05:00', 74, '2025-09-08 21:05:00', NULL, NULL, 'Inactive', '2025-09-08 21:05:00'),
(15, 'T-1-7-1-1756793223', '2025-09-09 01:18:22', 74, '2025-09-09 01:18:22', NULL, NULL, 'Inactive', '2025-09-09 01:18:22'),
(16, 'T-1-7-1-1756793223', '2025-09-09 01:26:53', 74, '2025-09-09 01:26:53', NULL, NULL, 'Inactive', '2025-09-09 01:26:53'),
(17, 'T-1-7-1-1756793223', '2025-09-09 03:08:53', 74, '2025-09-09 03:08:53', NULL, NULL, 'Inactive', '2025-09-09 03:08:53'),
(18, 'T-1-7-1-1756793223', '2025-09-09 03:20:02', 74, '2025-09-09 03:20:02', NULL, NULL, 'Inactive', '2025-09-09 03:20:02'),
(19, 'T-1-7-1-1756793223', '2025-09-09 03:22:20', 74, '2025-09-09 03:22:20', NULL, NULL, 'Inactive', '2025-09-09 03:22:20'),
(20, 'T-1-7-1-1756793223', '2025-09-09 03:31:47', 74, '2025-09-09 03:31:47', NULL, NULL, 'Inactive', '2025-09-09 03:31:47'),
(21, 'T-1-7-1-1756793223', '2025-09-09 03:38:56', 74, '2025-09-09 03:38:56', NULL, NULL, 'Inactive', '2025-09-09 03:38:56'),
(22, 'T-1-7-1-1756793223', '2025-09-09 15:10:23', 74, '2025-09-09 15:10:23', NULL, NULL, 'Inactive', '2025-09-09 15:10:23'),
(23, 'T-1-7-1-1756793223', '2025-09-09 15:13:58', 74, '2025-09-09 15:13:58', NULL, NULL, 'Inactive', '2025-09-09 15:13:58'),
(24, 'T-1-7-1-1756793223', '2025-09-09 15:18:39', 74, '2025-09-09 15:18:39', NULL, NULL, 'Inactive', '2025-09-09 15:18:39'),
(25, 'T-1-7-1-1756793223', '2025-09-09 15:21:29', 74, '2025-09-09 15:21:29', NULL, NULL, 'Inactive', '2025-09-09 15:21:29'),
(26, 'T-1-7-1-1756793223', '2025-09-09 15:25:42', 74, '2025-09-09 15:25:42', NULL, NULL, 'Inactive', '2025-09-09 15:25:42'),
(27, 'T-1-7-1-1756793223', '2025-09-09 17:52:49', 74, '2025-09-09 17:52:49', NULL, NULL, 'Inactive', '2025-09-09 17:52:49'),
(28, 'T-1-7-1-1756793223', '2025-09-09 17:57:09', 74, '2025-09-09 17:57:09', NULL, NULL, 'Inactive', '2025-09-09 17:57:09'),
(29, 'T-1-7-1-1756793223', '2025-09-09 18:21:40', 74, '2025-09-09 18:21:40', NULL, NULL, 'Inactive', '2025-09-09 18:21:40'),
(30, 'T-1-7-1-1756793223', '2025-09-09 18:46:58', 74, '2025-09-09 18:46:58', NULL, NULL, 'Inactive', '2025-09-09 18:46:58'),
(31, 'T-1-7-1-1756793223', '2025-09-09 18:49:12', 74, '2025-09-09 18:49:12', NULL, NULL, 'Inactive', '2025-09-09 18:49:12'),
(32, 'T-1-7-1-1756793223', '2025-09-09 18:56:27', 74, '2025-09-09 18:56:27', NULL, NULL, 'Inactive', '2025-09-09 18:56:27'),
(33, 'T-1-7-1-1756793223', '2025-09-10 01:55:26', 74, '2025-09-10 01:55:26', NULL, NULL, 'Inactive', '2025-09-10 01:55:26'),
(34, 'T-1-7-1-1756793223', '2025-09-10 02:00:21', 74, '2025-09-10 02:00:21', NULL, NULL, 'Inactive', '2025-09-10 02:00:21'),
(35, 'T-1-7-1-1756793223', '2025-09-10 02:09:15', 74, '2025-09-10 02:09:15', NULL, NULL, 'Inactive', '2025-09-10 02:09:15'),
(36, 'T-1-7-1-1756793223', '2025-09-10 15:43:01', 74, '2025-09-10 15:43:01', NULL, NULL, 'Inactive', '2025-09-10 15:43:01'),
(37, 'T-1-7-1-1756793223', '2025-09-10 18:09:23', 74, '2025-09-10 18:09:23', NULL, NULL, 'Inactive', '2025-09-10 18:09:23'),
(38, 'T-1-7-1-1756793223', '2025-09-10 18:29:33', 74, '2025-09-10 18:29:33', NULL, NULL, 'Inactive', '2025-09-10 18:29:33'),
(39, 'T-1-7-1-1756793223', '2025-09-10 19:18:33', 74, '2025-09-11 11:45:12', 42, 'approve', 'Inactive', '2025-09-10 19:18:33'),
(40, 'T-1-7-1-1756793223', '2025-09-11 23:18:26', 74, '2025-09-11 23:18:26', NULL, NULL, 'Inactive', '2025-09-11 23:18:26'),
(41, 'T-1-7-1-1756793223', '2025-09-12 02:32:03', 88, '2025-09-12 02:32:03', NULL, NULL, 'Inactive', '2025-09-12 02:32:03'),
(42, 'T-1-7-1-1756793223', '2025-09-12 14:15:52', 88, '2025-09-12 14:15:52', NULL, NULL, 'Inactive', '2025-09-12 14:15:52'),
(43, 'T-1-7-1-1756793223', '2025-09-12 15:37:33', 88, '2025-09-12 15:37:33', NULL, NULL, 'Inactive', '2025-09-12 15:37:33'),
(44, 'T-1-7-1-1756793223', '2025-09-12 16:55:43', 74, '2025-09-12 16:55:43', NULL, NULL, 'Inactive', '2025-09-12 16:55:43'),
(45, 'T-1-7-1-1756793223', '2025-09-12 18:24:58', 74, '2025-09-12 18:24:58', NULL, NULL, 'Inactive', '2025-09-12 18:24:58'),
(46, 'T-1-7-1-1756793223', '2025-09-16 11:26:34', 74, '2025-09-16 11:26:34', NULL, NULL, 'Inactive', '2025-09-16 11:26:34'),
(47, 'T-1-7-1-1756793223', '2025-09-16 17:23:35', 88, '2025-09-17 00:08:27', 42, 'approve', 'Active', '2025-09-16 17:23:35'),
(48, 'T-1-7-1-1759907854', '2025-10-08 12:57:07', 74, '2025-10-08 12:57:07', NULL, NULL, 'Inactive', '2025-10-08 12:57:07'),
(49, 'T-3-7-1-1759911286', '2025-10-08 15:51:52', 74, '2025-10-08 15:55:49', 42, 'approve', 'Active', '2025-10-08 15:51:52'),
(50, 'T-1-7-1-1759907854', '2025-10-08 16:31:34', 74, '2025-10-08 16:33:12', 42, 'approve', 'Inactive', '2025-10-08 16:31:34'),
(51, 'T-1-7-1-1759907854', '2025-10-08 17:47:03', 74, '2025-10-08 17:47:03', NULL, NULL, 'Inactive', '2025-10-08 17:47:03'),
(52, 'T-1-7-1-1759907854', '2025-10-08 17:59:01', 74, '2025-10-08 18:00:57', 42, 'approve', 'Inactive', '2025-10-08 17:59:01'),
(53, 'T-1-7-1-1759907854', '2025-10-08 18:03:41', 74, '2025-10-08 18:05:38', 42, 'approve', 'Active', '2025-10-08 18:03:41'),
(54, 'T-1-7-2-1759907854', '2025-10-08 18:50:05', NULL, '2025-10-08 18:50:05', 42, 'approve', 'Inactive', '2025-10-08 18:50:05'),
(55, 'T-4-7-1-1759946634', '2025-10-08 23:40:35', 74, '2025-10-08 23:40:35', NULL, NULL, 'Active', '2025-10-08 23:40:35'),
(56, 'T-1-7-2-1760164349', '2025-10-11 12:29:52', NULL, '2025-10-11 12:29:52', 42, 'approve', 'Active', '2025-10-11 12:29:52'),
(57, 'T-1-7-1-1760164349', '2025-10-11 12:42:57', 74, '2025-10-11 12:47:31', 42, 'approve', 'Active', '2025-10-11 12:42:57'),
(58, 'T-1-7-3-1760164349', '2025-10-11 17:25:34', NULL, '2025-10-11 17:25:34', 42, 'approve', 'Active', '2025-10-11 17:25:34'),
(59, 'T-1-7-6-1760164349', '2025-10-11 17:26:13', NULL, '2025-10-11 17:26:13', 42, 'approve', 'Active', '2025-10-11 17:26:13');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_test_schedules_tracking`
--

CREATE TABLE `tbl_test_schedules_tracking` (
  `test_sch_id` int NOT NULL,
  `unit_id` int DEFAULT NULL,
  `equip_id` int DEFAULT NULL,
  `test_id` int DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `test_wf_id` varchar(45) DEFAULT NULL,
  `val_wf_id` varchar(45) DEFAULT NULL,
  `test_wf_current_stage` varchar(45) DEFAULT NULL,
  `stage_assigned_datetime` datetime DEFAULT NULL,
  `test_wf_planned_start_date` date DEFAULT NULL,
  `test_wf_planned_end_date` date DEFAULT NULL,
  `test_conducted_date` date DEFAULT NULL,
  `certi_submission_date` date DEFAULT NULL,
  `test_performed_by` varchar(45) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `engineering_test_approval_datetime` datetime DEFAULT NULL,
  `engineering_test_approved_by` varchar(45) DEFAULT NULL,
  `engineering_test_approval_remarks` varchar(500) DEFAULT NULL,
  `qa_test_approval_datetime` datetime DEFAULT NULL,
  `qa_test_approved_by` varchar(45) DEFAULT NULL,
  `qa_test_approval_remarks` varchar(500) DEFAULT NULL,
  `status` varchar(45) DEFAULT 'Active',
  `created_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_modified_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `upload_doc1_path` varchar(200) DEFAULT NULL,
  `upload_doc2_path` varchar(200) DEFAULT NULL,
  `upload_doc3_path` varchar(200) DEFAULT NULL,
  `upload_doc4_path` varchar(200) DEFAULT NULL,
  `upload_doc5_path` varchar(200) DEFAULT NULL,
  `routine_test_request_id` int DEFAULT NULL,
  `test_type` char(1) DEFAULT NULL,
  `auto_schedule_processed` char(1) DEFAULT 'N' COMMENT 'Flag to prevent duplicate processing',
  `auto_schedule_trigger_date` datetime DEFAULT NULL COMMENT 'When auto-scheduling was triggered',
  `data_entry_mode` enum('online','offline') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_test_schedules_tracking`
--

INSERT INTO `tbl_test_schedules_tracking` (`test_sch_id`, `unit_id`, `equip_id`, `test_id`, `vendor_id`, `test_wf_id`, `val_wf_id`, `test_wf_current_stage`, `stage_assigned_datetime`, `test_wf_planned_start_date`, `test_wf_planned_end_date`, `test_conducted_date`, `certi_submission_date`, `test_performed_by`, `remarks`, `engineering_test_approval_datetime`, `engineering_test_approved_by`, `engineering_test_approval_remarks`, `qa_test_approval_datetime`, `qa_test_approved_by`, `qa_test_approval_remarks`, `status`, `created_date_time`, `last_modified_date_time`, `upload_doc1_path`, `upload_doc2_path`, `upload_doc3_path`, `upload_doc4_path`, `upload_doc5_path`, `routine_test_request_id`, `test_type`, `auto_schedule_processed`, `auto_schedule_trigger_date`, `data_entry_mode`) VALUES
(11632, 7, 1, 1, 3, 'T-1-7-1-1760164349', 'V-1-7-1760163302-6M', '5', '2025-10-11 12:02:29', NULL, NULL, '2025-10-11', '2025-10-11', '46', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', '2025-10-11 12:02:29', '2025-10-11 12:37:31', NULL, NULL, NULL, NULL, NULL, NULL, 'V', 'N', NULL, 'online'),
(11633, 7, 1, 2, 3, 'T-1-7-2-1760164349', 'V-1-7-1760163302-6M', '5', '2025-10-11 12:02:29', NULL, NULL, '2025-10-11', '2025-10-11', '46', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', '2025-10-11 12:02:29', '2025-10-11 12:02:29', NULL, NULL, NULL, NULL, NULL, NULL, 'V', 'N', NULL, NULL),
(11634, 7, 1, 3, 3, 'T-1-7-3-1760164349', 'V-1-7-1760163302-6M', '5', '2025-10-11 12:02:29', NULL, NULL, '2025-10-11', '2025-10-11', '46', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', '2025-10-11 12:02:29', '2025-10-11 12:02:29', NULL, NULL, NULL, NULL, NULL, NULL, 'V', 'N', NULL, NULL),
(11635, 7, 1, 6, 3, 'T-1-7-6-1760164349', 'V-1-7-1760163302-6M', '5', '2025-10-11 12:02:29', NULL, NULL, '2025-10-11', '2025-10-11', '46', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', '2025-10-11 12:02:29', '2025-10-11 12:02:29', NULL, NULL, NULL, NULL, NULL, NULL, 'V', 'N', NULL, NULL),
(11636, 7, 1, 9, 0, 'T-1-7-9-1760164349', 'V-1-7-1760163302-6M', '5', '2025-10-11 12:02:29', NULL, NULL, '2025-10-11', '2025-10-11', '42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Active', '2025-10-11 12:02:29', '2025-10-11 12:02:29', NULL, NULL, NULL, NULL, NULL, NULL, 'V', 'N', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_training_details`
--

CREATE TABLE `tbl_training_details` (
  `id` int NOT NULL,
  `val_wf_id` varchar(45) DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `file_name` varchar(500) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` varchar(45) DEFAULT NULL,
  `record_status` varchar(45) DEFAULT 'Active',
  `record_created_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `record_last_modification_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_training_details`
--

INSERT INTO `tbl_training_details` (`id`, `val_wf_id`, `department_id`, `user_id`, `file_name`, `file_path`, `file_size`, `record_status`, `record_created_datetime`, `record_last_modification_datetime`) VALUES
(913, 'V-1-7-1760163302-6M', 1, 42, 'Bombay HC Order.pdf', 'uploads/1760164347_0_Bombay HC Order.pdf', '329945', 'Active', '2025-10-11 12:02:27', '2025-10-11 12:02:27'),
(914, 'V-1-7-1760163302-6M', 8, 46, 'Bombay HC Order.pdf', 'uploads/1760164347_1_Bombay HC Order.pdf', '329945', 'Active', '2025-10-11 12:02:27', '2025-10-11 12:02:27');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_uploads`
--

CREATE TABLE `tbl_uploads` (
  `upload_id` int NOT NULL,
  `upload_path_test_certificate` varchar(500) DEFAULT NULL,
  `upload_path_master_certificate` varchar(500) DEFAULT NULL,
  `upload_path_raw_data` varchar(500) DEFAULT NULL,
  `upload_path_other_doc` varchar(500) DEFAULT NULL,
  `upload_remarks` varchar(500) DEFAULT NULL,
  `upload_type` varchar(100) DEFAULT NULL,
  `upload_action` varchar(45) DEFAULT NULL,
  `uploaded_by` int DEFAULT NULL,
  `uploaded_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `upload_status` varchar(45) DEFAULT 'Active',
  `test_wf_id` varchar(50) DEFAULT NULL,
  `test_id` int DEFAULT NULL,
  `val_wf_id` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_uploads`
--

INSERT INTO `tbl_uploads` (`upload_id`, `upload_path_test_certificate`, `upload_path_master_certificate`, `upload_path_raw_data`, `upload_path_other_doc`, `upload_remarks`, `upload_type`, `upload_action`, `uploaded_by`, `uploaded_datetime`, `upload_status`, `test_wf_id`, `test_id`, `val_wf_id`) VALUES
(9458, '../../uploads/T-1-7-2-1760164349-tcert-1760164980-a0a638d8-eb1e687571d66075-IR.pdf', '../../uploads/T-1-7-2-1760164349-mcert-1760164980-a0a638d8-62e0fdc214d014f5.pdf', '../../uploads/T-1-7-2-1760164349-data-1760164980-a0a638d8-f14025b0359e54eb.pdf', '', NULL, NULL, 'Approved', 74, '2025-10-11 12:13:00', 'Active', 'T-1-7-2-1760164349', 2, 'V-1-7-1760163302-6M'),
(9459, '../../uploads/TestCertificate-T-1-7-1-1760164349-1760167171-QA-f031be0c.pdf', NULL, '../../uploads/RawData-T-1-7-1-1760164349-1760167026.pdf', NULL, NULL, 'acph_test_documents', 'Approved', 74, '2025-10-11 12:42:57', 'Active', 'T-1-7-1-1760164349', 1, 'V-1-7-1760163302-6M'),
(9460, NULL, 'uploads/certificates/cert_INs234_1756763772.pdf', NULL, NULL, NULL, 'instrument_calibration_certificate', 'Approved', 74, '2025-10-11 12:42:57', 'Active', 'T-1-7-1-1760164349', 1, 'V-1-7-1760163302-6M'),
(9461, '../../uploads/T-1-7-3-1760164349-tcert-1760183605-ac09b05c-d7f971d8188eccad-IR.pdf', '../../uploads/T-1-7-3-1760164349-mcert-1760183605-ac09b05c-9be0bf974754557d.pdf', '../../uploads/T-1-7-3-1760164349-data-1760183605-ac09b05c-41514256a02cb8c3.pdf', '', NULL, NULL, 'Approved', 74, '2025-10-11 17:23:25', 'Active', 'T-1-7-3-1760164349', 3, 'V-1-7-1760163302-6M'),
(9462, '../../uploads/T-1-7-6-1760164349-tcert-1760183657-643a9d18-a41e26ede56dd3cb-IR.pdf', '../../uploads/T-1-7-6-1760164349-mcert-1760183657-643a9d18-696542b5e3ad91ca.pdf', '../../uploads/T-1-7-6-1760164349-data-1760183657-643a9d18-16bc11eadb73373d.pdf', '', NULL, NULL, 'Approved', 74, '2025-10-11 17:24:17', 'Active', 'T-1-7-6-1760164349', 6, 'V-1-7-1760163302-6M');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_val_schedules`
--

CREATE TABLE `tbl_val_schedules` (
  `val_sch_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `equip_id` int NOT NULL,
  `val_wf_id` varchar(45) NOT NULL,
  `val_wf_planned_start_date` date NOT NULL,
  `val_wf_planned_end_date` date DEFAULT NULL,
  `val_wf_status` varchar(45) DEFAULT 'Active',
  `created_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_modified_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_adhoc` char(4) DEFAULT NULL,
  `requested_by_user_id` int DEFAULT NULL,
  `parent_val_wf_id` varchar(45) DEFAULT NULL COMMENT 'Parent validation for auto-created validations',
  `auto_created` char(1) DEFAULT 'N' COMMENT 'Flag indicating auto-created validation',
  `actual_execution_date` date DEFAULT NULL COMMENT 'Actual test execution date for reference',
  `frequency_code` varchar(5) DEFAULT NULL COMMENT 'Validation frequency (Y, 2Y)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_val_schedules`
--

INSERT INTO `tbl_val_schedules` (`val_sch_id`, `unit_id`, `equip_id`, `val_wf_id`, `val_wf_planned_start_date`, `val_wf_planned_end_date`, `val_wf_status`, `created_date_time`, `last_modified_date_time`, `is_adhoc`, `requested_by_user_id`, `parent_val_wf_id`, `auto_created`, `actual_execution_date`, `frequency_code`) VALUES
(10051, 7, 1, 'V-1-7-1760163302-6M', '2025-09-14', NULL, 'Active', '2025-10-11 11:46:37', '2025-10-11 11:46:37', NULL, NULL, NULL, 'N', NULL, NULL),
(10052, 7, 2, 'V-2-7-1760163401-6M', '2025-02-07', NULL, 'Active', '2025-10-11 11:46:37', '2025-10-11 11:46:37', NULL, NULL, NULL, 'N', NULL, NULL),
(10053, 7, 2, 'V-2-7-1760163401-Y', '2025-08-06', NULL, 'Active', '2025-10-11 11:46:37', '2025-10-11 11:46:37', NULL, NULL, NULL, 'N', NULL, NULL),
(10054, 7, 3, 'V-3-7-1760163401-Y', '2025-09-03', NULL, 'Active', '2025-10-11 11:46:37', '2025-10-11 11:46:37', NULL, NULL, NULL, 'N', NULL, NULL),
(10055, 7, 4, 'V-4-7-1760163401-6M', '2025-09-08', NULL, 'Active', '2025-10-11 11:46:37', '2025-10-11 11:46:37', NULL, NULL, NULL, 'N', NULL, NULL),
(10056, 7, 5, 'V-5-7-1760163401-Y', '2025-09-20', NULL, 'Active', '2025-10-11 11:46:37', '2025-10-11 11:46:37', NULL, NULL, NULL, 'N', NULL, NULL);

--
-- Triggers `tbl_val_schedules`
--
DELIMITER $$
CREATE TRIGGER `tr_val_schedules_update_timestamp` BEFORE UPDATE ON `tbl_val_schedules` FOR EACH ROW BEGIN
    SET NEW.last_modified_date_time = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_val_wf_approval_tracking_details`
--

CREATE TABLE `tbl_val_wf_approval_tracking_details` (
  `val_wf_approval_trcking_id` int NOT NULL,
  `val_wf_id` varchar(45) NOT NULL,
  `iteration_id` int DEFAULT NULL,
  `iteration_start_datetime` datetime DEFAULT NULL,
  `iteration_completion_status` varchar(45) DEFAULT NULL,
  `iteration_rejected_datetime` datetime DEFAULT NULL,
  `iteration_rejected_by` int DEFAULT NULL,
  `iteration_status` varchar(45) DEFAULT NULL,
  `engg_app_submission_date_time` datetime DEFAULT NULL,
  `engg_app_sbmitted_by` varchar(45) DEFAULT NULL,
  `level1_user_dept_approval_datetime` datetime DEFAULT NULL,
  `level1_user_dept_approval_by` int DEFAULT NULL,
  `level1_user_dept_approval_remarks` varchar(500) DEFAULT NULL,
  `level1_eng_approval_datetime` datetime DEFAULT NULL,
  `level1_eng_approval_by` int DEFAULT NULL,
  `level1_eng_approval_remarks` varchar(500) DEFAULT NULL,
  `level1_hse_approval_datetime` datetime DEFAULT NULL,
  `level1_hse_approval_by` int DEFAULT NULL,
  `level1_hse_approval_remarks` varchar(500) DEFAULT NULL,
  `level1_qc_approval_datetime` datetime DEFAULT NULL,
  `level1_qc_approval_by` int DEFAULT NULL,
  `level1_qc_approval_remarks` varchar(500) DEFAULT NULL,
  `level1_qa_approval_datetime` datetime DEFAULT NULL,
  `level1_qa_approval_by` int DEFAULT NULL,
  `level1_qa_approval_remarks` varchar(500) DEFAULT NULL,
  `level2_head_qa_approval_datetime` datetime DEFAULT NULL,
  `level2_head_qa_approval_by` int DEFAULT NULL,
  `level2_head_qa_approval_remarks` varchar(500) DEFAULT NULL,
  `level3_unit_head_approval_datetime` datetime DEFAULT NULL,
  `level3_unit_head_approval_by` int DEFAULT NULL,
  `level3_unit_head_approval_remarks` varchar(500) DEFAULT NULL,
  `protocol_report_path` varchar(500) DEFAULT NULL,
  `level2_unit_head_approval_datetime` datetime DEFAULT NULL,
  `level2_unit_head_approval_by` int DEFAULT NULL,
  `level2_unit_head_approval_remarks` varchar(500) DEFAULT NULL,
  `level3_head_qa_approval_datetime` datetime DEFAULT NULL,
  `level3_head_qa_approval_by` int DEFAULT NULL,
  `level3_head_qa_approval_remarks` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_val_wf_approval_tracking_details`
--

INSERT INTO `tbl_val_wf_approval_tracking_details` (`val_wf_approval_trcking_id`, `val_wf_id`, `iteration_id`, `iteration_start_datetime`, `iteration_completion_status`, `iteration_rejected_datetime`, `iteration_rejected_by`, `iteration_status`, `engg_app_submission_date_time`, `engg_app_sbmitted_by`, `level1_user_dept_approval_datetime`, `level1_user_dept_approval_by`, `level1_user_dept_approval_remarks`, `level1_eng_approval_datetime`, `level1_eng_approval_by`, `level1_eng_approval_remarks`, `level1_hse_approval_datetime`, `level1_hse_approval_by`, `level1_hse_approval_remarks`, `level1_qc_approval_datetime`, `level1_qc_approval_by`, `level1_qc_approval_remarks`, `level1_qa_approval_datetime`, `level1_qa_approval_by`, `level1_qa_approval_remarks`, `level2_head_qa_approval_datetime`, `level2_head_qa_approval_by`, `level2_head_qa_approval_remarks`, `level3_unit_head_approval_datetime`, `level3_unit_head_approval_by`, `level3_unit_head_approval_remarks`, `protocol_report_path`, `level2_unit_head_approval_datetime`, `level2_unit_head_approval_by`, `level2_unit_head_approval_remarks`, `level3_head_qa_approval_datetime`, `level3_head_qa_approval_by`, `level3_head_qa_approval_remarks`) VALUES
(796, 'V-1-7-1760163302-6M', 1, '2025-10-11 17:31:10', 'complete', NULL, NULL, 'Active', '2025-10-11 17:31:10', '42', NULL, 0, NULL, '2025-10-11 17:31:54', 42, 'Ok', NULL, 0, NULL, NULL, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/protocol-report-V-1-7-1760163302-6M.pdf', '2025-10-11 17:32:57', 54, 'ok', '2025-10-11 17:33:37', 41, 'ok');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_val_wf_schedule_requests`
--

CREATE TABLE `tbl_val_wf_schedule_requests` (
  `schedule_id` int NOT NULL,
  `schedule_year` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `schedule_generation_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `schedule_request_status` varchar(45) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_val_wf_schedule_requests`
--

INSERT INTO `tbl_val_wf_schedule_requests` (`schedule_id`, `schedule_year`, `unit_id`, `schedule_generation_datetime`, `schedule_request_status`) VALUES
(141, 2025, 7, '2025-10-11 11:45:01', '3');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_val_wf_tracking_details`
--

CREATE TABLE `tbl_val_wf_tracking_details` (
  `val_wf_tracking_id` int NOT NULL,
  `val_wf_id` varchar(45) NOT NULL,
  `equipment_id` int DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `actual_wf_start_datetime` datetime NOT NULL,
  `actual_wf_end_datetime` datetime DEFAULT NULL,
  `wf_initiated_by_user_id` int DEFAULT NULL,
  `status` varchar(45) DEFAULT 'Active',
  `created_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_modified_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `val_wf_current_stage` varchar(45) DEFAULT NULL,
  `stage_assigned_datetime` datetime DEFAULT NULL,
  `deviation_remark` varchar(500) DEFAULT NULL,
  `stage_before_termination` varchar(45) DEFAULT NULL COMMENT 'Stores the workflow stage before termination request was initiated',
  `tr_reviewer_remarks` varchar(200) DEFAULT NULL COMMENT 'Engineering Department Head remarks during termination review',
  `tr_approver_remarks` varchar(200) DEFAULT NULL COMMENT 'QA Head remarks during termination approval',
  `tr_termination_reason` varchar(100) DEFAULT NULL COMMENT 'Reason for termination from dropdown',
  `tr_termination_remarks` text COMMENT 'Free text remarks for termination request'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tbl_val_wf_tracking_details`
--

INSERT INTO `tbl_val_wf_tracking_details` (`val_wf_tracking_id`, `val_wf_id`, `equipment_id`, `unit_id`, `actual_wf_start_datetime`, `actual_wf_end_datetime`, `wf_initiated_by_user_id`, `status`, `created_date_time`, `last_modified_date_time`, `val_wf_current_stage`, `stage_assigned_datetime`, `deviation_remark`, `stage_before_termination`, `tr_reviewer_remarks`, `tr_approver_remarks`, `tr_termination_reason`, `tr_termination_remarks`) VALUES
(922, 'V-1-7-1760163302-6M', 1, 7, '2025-10-11 12:02:29', '2025-10-11 17:33:37', 42, 'Active', '2025-10-11 12:02:29', '2025-10-11 12:02:29', '5', '2025-10-11 17:33:37', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tests`
--

CREATE TABLE `tests` (
  `test_id` int NOT NULL,
  `test_name` varchar(100) DEFAULT NULL,
  `test_description` varchar(100) DEFAULT NULL,
  `test_performed_by` varchar(45) DEFAULT NULL,
  `test_status` varchar(45) DEFAULT 'Active',
  `dependent_tests` text,
  `paper_on_glass_enabled` enum('Yes','No') DEFAULT 'No',
  `test_created_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `test_last_modification_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `test_purpose` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tests`
--

INSERT INTO `tests` (`test_id`, `test_name`, `test_description`, `test_performed_by`, `test_status`, `dependent_tests`, `paper_on_glass_enabled`, `test_created_datetime`, `test_last_modification_datetime`, `test_purpose`) VALUES
(1, 'ACPH', 'Air changes per hours ', 'External', 'Active', 'NA', 'Yes', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE THE FLOWRATE AND AIR CHANGES PER HOUR IN A CLEAN ROOM'),
(2, 'Test002', 'Fresh air CFM', 'External', 'Active', NULL, 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE THE % CFM ACROSS FRESH AIR FILTER / FRESH AIR DUCT OF HVAC SYSTEM'),
(3, 'Test003', 'Return air CFM', 'External', 'Active', 'NA', 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE THE RETURN AIR CFM IN A CLEAN ROOM'),
(4, 'Test004', 'Relief air CFM', 'External', 'Active', NULL, 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE THE% CFM ACROSS RELIEF AIR FILTER OF HVAC SYSTEM'),
(6, 'Test006', 'Filter integrity test', 'External', 'Active', 'NA', 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'INSTALLED FILTER SYSTEM LEAKAGE TEST'),
(7, 'Test007', 'Dust collector/Scrubber/Point exhaust CFM', 'External', 'Active', NULL, 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE THE CFM IN POINT EXHAUST'),
(8, 'Test008', 'Temperature and relative humidity in the area', 'Internal', 'Active', NULL, 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE TEMPERATURE AND RELATIVE HUMIDITY IN THE AREA'),
(9, 'Test009', 'Differential pressure in the area', 'Internal', 'Active', NULL, 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE DIFFERENTIAL PRESSURE IN THE AREA'),
(10, 'Test010', 'Airflow direction test and visualization', 'Internal', 'Active', NULL, 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE AIRFLOW PATTERN IN THE AREA'),
(11, 'Test011', 'Particle matter count at rest condition', 'External', 'Active', NULL, 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE THE ACTUAL PARTICLE COUNT LEVEL “ AT REST ” OCCUPANCY STATE'),
(12, 'Test012', 'Particle matter count in operation condition', 'External', 'Active', NULL, 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE THE ACTUAL PARTICLE COUNT LEVEL “ IN OPERATION ” OCCUPANCY STATE'),
(13, 'Test013', 'Containment leakage test', 'External', 'Active', NULL, 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE IF THERE IS INTRUSION OF CONTAMINATED AIR INTO THE CLEAN ZONES FROM SURROUNDING NON CONTROLLED AREAS AT THE SAME OR DIFFERENT STATIC PRESSURE LEVEL.'),
(14, 'Test014', 'Area recovery/clean-up period study', 'External', 'Active', NULL, 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE THE AMOUNT OF TIME REQUIRED FOR A CLEAN ROOM TO ACHIEVE SPECIFIED AREA/ROOM CONDITION AFTER CONTAMINATION.'),
(15, 'Test015', 'Microbial count ', 'Internal', 'Active', NULL, 'No', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'TO DETERMINE MICROBIAL COUNT IN THE AREA'),
(16, 'Test016', 'To review all the Planned Preventive Maintenance & Filter Cleaning Activity records ', 'Internal', 'Active', NULL, 'No', '2024-02-13 10:40:05', '2024-02-13 10:40:05', 'All the Planned Preventive Maintenance & Filter Cleaning Activity records to be Reviewed since previous Periodic Performance Verification.  '),
(17, 'Test017', 'LEAD TIME STUDY', 'External', 'Active', NULL, 'No', '2024-09-05 00:27:00', '2024-09-05 00:27:00', 'TO DETERMINE THE LEAD TIME STUDY FOR THE EQUIPMENT '),
(18, 'Test018', 'AIR VELOCITY', 'External', 'Active', NULL, 'No', '2024-09-05 00:27:00', '2024-09-05 00:27:00', 'TO DETERMINE THE AIR VELOCITY FOR EQUIPMENT'),
(19, 'Test Test', 'Test Description', 'Internal', 'Active', NULL, 'No', '2025-05-26 16:21:19', '2025-05-26 16:21:19', 'Test Purpose not known'),
(20, 'Test', 'Sample Description', 'Internal', 'Active', NULL, 'No', '2025-06-15 23:57:32', '2025-06-15 23:57:32', 'Sample Purpose'),
(21, 'test120', 'Sample Test', 'Internal', 'Active', NULL, 'No', '2025-07-24 23:11:30', '2025-07-24 23:11:30', 'Sample Test'),
(22, 't', 'T', 'Internal', 'Active', NULL, 'No', '2025-08-31 02:06:43', '2025-08-31 02:06:43', 'T');

-- --------------------------------------------------------

--
-- Table structure for table `test_instruments`
--

CREATE TABLE `test_instruments` (
  `mapping_id` int NOT NULL,
  `test_val_wf_id` varchar(50) NOT NULL,
  `instrument_id` varchar(100) NOT NULL,
  `added_by` int NOT NULL,
  `added_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  `unit_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `test_instruments`
--

INSERT INTO `test_instruments` (`mapping_id`, `test_val_wf_id`, `instrument_id`, `added_by`, `added_date`, `is_active`, `unit_id`) VALUES
(1, 'T-1-7-1-1756793223', 'INs234', 74, '2025-09-02 09:11:08', 0, 0),
(2, 'T-1-7-1-1756793223', 'INs2346777', 74, '2025-09-02 09:23:41', 0, 0),
(3, 'T-1-7-1-1756793223', 'INST001', 74, '2025-09-02 09:24:07', 0, 0),
(4, 'T-1-7-1-1756793223', 'INs234', 74, '2025-09-04 19:40:35', 0, 0),
(5, 'T-1-7-1-1756793223', 'INs2346777', 74, '2025-09-08 19:59:11', 0, 0),
(6, 'T-1-7-1-1756793223', 'INs2346777sss', 74, '2025-09-08 20:07:03', 0, 0),
(7, 'T-1-7-1-1756793223', 'INs2346777', 74, '2025-09-08 20:47:42', 0, 0),
(8, 'T-1-7-1-1756793223', 'INs2346777sss', 74, '2025-09-08 20:48:01', 0, 0),
(9, 'T-1-7-1-1756793223', 'INs2346777', 74, '2025-09-08 21:03:54', 0, 0),
(10, 'T-1-7-1-1756793223', 'INs2346777', 74, '2025-09-08 21:17:49', 0, 0),
(11, 'T-1-7-1-1756793223', 'INs2346777', 74, '2025-09-08 21:18:17', 0, 0),
(12, 'T-1-7-1-1756793223', 'INs2346777', 74, '2025-09-08 21:21:21', 0, 0),
(13, 'T-1-7-1-1756793223', 'INs234', 74, '2025-09-09 18:54:44', 0, 0),
(14, 'T-1-7-1-1756793223', 'INs234', 74, '2025-09-09 19:21:19', 1, 0),
(15, 'T-1-7-1-1756793223', 'INs2346777sss', 74, '2025-09-09 19:21:53', 1, 0),
(16, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 12:26:38', 0, 0),
(17, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 12:29:23', 0, 0),
(18, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 12:56:55', 0, 0),
(19, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 13:00:08', 0, 0),
(20, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 13:01:51', 0, 0),
(21, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 13:04:27', 0, 0),
(22, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 13:05:10', 0, 0),
(23, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 13:05:48', 0, 0),
(24, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 13:07:53', 0, 0),
(25, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 13:08:39', 0, 0),
(26, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 13:12:44', 0, 0),
(27, 'T-1-7-1-1759219060', 'INs234', 74, '2025-10-07 13:15:07', 1, 0),
(28, 'T-1-7-1-1759907854', 'INs234', 74, '2025-10-08 07:24:57', 1, 0),
(29, 'T-3-7-1-1759911286', 'INs234', 74, '2025-10-08 08:53:31', 1, 0),
(30, 'T-4-7-1-1759946634', 'INs234', 74, '2025-10-08 18:09:02', 1, 0),
(31, 'T-1-7-1-1760164349', 'INs234', 74, '2025-10-11 07:06:07', 1, 0),
(32, 'T-1-7-1-1760164349', 'INST003', 74, '2025-10-11 07:06:19', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `test_specific_data`
--

CREATE TABLE `test_specific_data` (
  `id` int NOT NULL,
  `test_val_wf_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Test workflow ID from tbl_test_schedules_tracking',
  `section_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of test section (acph, airflow, temperature, etc.)',
  `data_json` json NOT NULL COMMENT 'JSON storage for test-specific field data',
  `entered_by` int NOT NULL COMMENT 'User who first entered the data',
  `entered_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When data was first entered',
  `modified_by` int DEFAULT NULL COMMENT 'User who last modified the data',
  `modified_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When data was last modified',
  `unit_id` int NOT NULL COMMENT 'Unit ID for data segregation',
  `filter_id` int DEFAULT NULL COMMENT 'References filter_id from filters table',
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Active',
  `creation_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_modification_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Storage for test-specific data sections in JSON format';

--
-- Dumping data for table `test_specific_data`
--

INSERT INTO `test_specific_data` (`id`, `test_val_wf_id`, `section_type`, `data_json`, `entered_by`, `entered_date`, `modified_by`, `modified_date`, `unit_id`, `filter_id`, `status`, `creation_datetime`, `last_modification_datetime`) VALUES
(1, 'T-1-7-1-1756793223', 'acph_filter_1', '{\"average\": \"5.00\", \"readings\": {\"reading_1\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"1\", \"filter_id\": \"1\", \"flow_rate\": \"600\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-04 20:13:39', 74, '2025-09-08 19:14:43', 7, 1, 'Inactive', '2025-09-08 03:41:09', '2025-09-09 00:44:43'),
(2, 'T-1-7-1-1756793223', 'acph_filter_2', '{\"average\": \"150.40\", \"readings\": {\"reading_1\": {\"value\": \"67\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"54\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"555\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"43\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"33\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"7.70\", \"filter_id\": \"2\", \"flow_rate\": \"654\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"individual\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-04 21:03:15', 74, '2025-09-08 19:14:43', 7, 2, 'Inactive', '2025-09-08 03:41:09', '2025-09-09 00:44:43'),
(9, 'T-1-7-1-1756793223', 'acph_filter_1', '{\"average\": \"76.60\", \"readings\": {\"reading_1\": {\"value\": \"82\"}, \"reading_2\": {\"value\": \"83\"}, \"reading_3\": {\"value\": \"84\"}, \"reading_4\": {\"value\": \"47\"}, \"reading_5\": {\"value\": \"87\"}}, \"cell_area\": \"7.5\", \"filter_id\": \"1\", \"flow_rate\": \"600\", \"filter_instrument\": \"\", \"global_instrument\": \"\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-08 19:08:22', NULL, '2025-09-08 19:14:43', 7, 1, 'Inactive', '2025-09-09 00:38:22', '2025-09-09 00:44:43'),
(10, 'T-1-7-1-1756793223', 'acph_filter_1', '{\"average\": \"1.00\", \"readings\": {\"reading_1\": {\"value\": \"1\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"1\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"1\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"1\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"1\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"5\", \"filter_id\": \"1\", \"flow_rate\": \"6\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-08 19:42:45', NULL, '2025-09-08 22:00:07', 7, 1, 'Inactive', '2025-09-09 01:12:45', '2025-09-09 03:30:07'),
(11, 'T-1-7-1-1756793223', 'acph_filter_2', '{\"average\": \"44.00\", \"readings\": {\"reading_1\": {\"value\": \"50\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"40\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"44\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"43\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"43\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"7.5\", \"filter_id\": \"2\", \"flow_rate\": \"123\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-08 19:43:41', NULL, '2025-09-08 21:21:37', 7, 2, 'Inactive', '2025-09-09 01:13:41', '2025-09-09 02:51:37'),
(12, 'T-1-7-1-1756793223', 'acph_filter_2', '{\"average\": \"44.00\", \"readings\": {\"reading_1\": {\"value\": \"50\", \"instrument_id\": \"INs2346777\"}, \"reading_2\": {\"value\": \"40\", \"instrument_id\": \"INs2346777\"}, \"reading_3\": {\"value\": \"44\", \"instrument_id\": \"INs2346777\"}, \"reading_4\": {\"value\": \"43\", \"instrument_id\": \"INs2346777\"}, \"reading_5\": {\"value\": \"43\", \"instrument_id\": \"INs2346777\"}}, \"cell_area\": \"7.5\", \"filter_id\": \"2\", \"flow_rate\": \"123\", \"instruments_used\": [\"INs2346777\"], \"filter_instrument\": \"INs2346777\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-08 21:21:37', NULL, '2025-09-08 22:00:07', 7, 2, 'Inactive', '2025-09-09 02:51:37', '2025-09-09 03:30:07'),
(13, 'T-1-7-1-1756793223', 'acph_filter_1', '{\"average\": \"50.00\", \"readings\": {\"reading_1\": {\"value\": \"54\", \"instrument_id\": \"INs2346777\"}, \"reading_2\": {\"value\": \"55\", \"instrument_id\": \"INs2346777\"}, \"reading_3\": {\"value\": \"55\", \"instrument_id\": \"INs2346777\"}, \"reading_4\": {\"value\": \"54\", \"instrument_id\": \"INs2346777\"}, \"reading_5\": {\"value\": \"32\", \"instrument_id\": \"INs2346777\"}}, \"cell_area\": \"7.7\", \"filter_id\": \"1\", \"flow_rate\": \"657\", \"instruments_used\": [\"INs2346777\"], \"filter_instrument\": \"INs2346777\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-08 22:01:05', NULL, '2025-09-09 17:31:57', 7, 1, 'Inactive', '2025-09-09 03:31:05', '2025-09-09 23:01:57'),
(14, 'T-1-7-1-1756793223', 'acph_filter_2', '{\"average\": \"55.00\", \"readings\": {\"reading_1\": {\"value\": \"100\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"44\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"44\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"44\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"43\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"6.5\", \"filter_id\": \"2\", \"flow_rate\": \"578\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-08 22:01:42', NULL, '2025-09-09 17:31:57', 7, 2, 'Inactive', '2025-09-09 03:31:42', '2025-09-09 23:01:57'),
(15, 'T-1-7-1-1756793223', 'acph_filter_1', '{\"average\": \"29.20\", \"readings\": {\"reading_1\": {\"value\": \"50\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"30\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"22\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"22\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"22\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"7.5\", \"filter_id\": \"1\", \"flow_rate\": \"600\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"\", \"global_instrument\": \"INs234\", \"filter_instrument_mode\": \"\", \"global_instrument_mode\": \"single\"}', 74, '2025-09-09 20:19:55', NULL, '2025-09-10 06:52:40', 7, 1, 'Inactive', '2025-09-10 01:49:55', '2025-09-10 12:22:40'),
(16, 'T-1-7-1-1756793223', 'acph_filter_2', '{\"average\": \"38.60\", \"readings\": {\"reading_1\": {\"value\": \"23\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"55\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"55\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"55\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"6,7\", \"filter_id\": \"2\", \"flow_rate\": \"500\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"\", \"global_instrument\": \"INs234\", \"filter_instrument_mode\": \"\", \"global_instrument_mode\": \"single\"}', 74, '2025-09-09 20:20:11', NULL, '2025-09-10 06:52:40', 7, 2, 'Inactive', '2025-09-10 01:50:11', '2025-09-10 12:22:40'),
(17, 'T-1-7-1-1756793223', 'acph_filter_1', '{\"average\": \"NA\", \"readings\": {\"reading_1\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_2\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_3\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_4\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_5\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}}, \"cell_area\": \"NA\", \"filter_id\": \"1\", \"flow_rate\": \"152\", \"instruments_used\": [\"INs2346777sss\"], \"filter_instrument\": \"INs2346777sss\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-10 10:12:20', NULL, '2025-09-10 12:58:03', 7, 1, 'Inactive', '2025-09-10 15:42:20', '2025-09-10 18:28:03'),
(18, 'T-1-7-1-1756793223', 'acph_filter_2', '{\"average\": \"NA\", \"readings\": {\"reading_1\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_2\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_3\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_4\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_5\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}}, \"cell_area\": \"NA\", \"filter_id\": \"2\", \"flow_rate\": \"221\", \"instruments_used\": [\"INs2346777sss\"], \"filter_instrument\": \"INs2346777sss\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-10 10:12:48', NULL, '2025-09-10 12:58:03', 7, 2, 'Inactive', '2025-09-10 15:42:48', '2025-09-10 18:28:03'),
(19, 'T-1-7-1-1756793223', 'acph_filter_1', '{\"average\": \"NA\", \"readings\": {\"reading_1\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_2\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_3\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_4\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_5\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}}, \"cell_area\": \"NA\", \"filter_id\": \"1\", \"flow_rate\": \"600\", \"instruments_used\": [\"INs2346777sss\"], \"filter_instrument\": \"INs2346777sss\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-10 12:59:07', NULL, '2025-09-10 13:47:18', 7, 1, 'Inactive', '2025-09-10 18:29:07', '2025-09-10 19:17:18'),
(20, 'T-1-7-1-1756793223', 'acph_filter_2', '{\"average\": \"NA\", \"readings\": {\"reading_1\": {\"value\": \"NA\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"NA\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"NA\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"NA\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"NA\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"NA\", \"filter_id\": \"2\", \"flow_rate\": \"700\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-10 12:59:26', NULL, '2025-09-10 13:47:18', 7, 2, 'Inactive', '2025-09-10 18:29:26', '2025-09-10 19:17:18'),
(21, 'T-1-7-1-1756793223', 'acph_filter_1', '{\"average\": \"NA\", \"readings\": {\"reading_1\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_2\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_3\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_4\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}, \"reading_5\": {\"value\": \"NA\", \"instrument_id\": \"INs2346777sss\"}}, \"cell_area\": \"7.5\", \"filter_id\": \"1\", \"flow_rate\": \"599.98\", \"instruments_used\": [\"INs2346777sss\"], \"filter_instrument\": \"INs2346777sss\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-10 13:48:07', NULL, '2025-09-11 09:27:51', 7, 1, 'Inactive', '2025-09-10 19:18:07', '2025-09-11 14:57:51'),
(22, 'T-1-7-1-1756793223', 'acph_filter_2', '{\"average\": \"NA\", \"readings\": {\"reading_1\": {\"value\": \"NA\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"NA\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"NA\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"NA\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"NA\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"7.5\", \"filter_id\": \"2\", \"flow_rate\": \"500\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-10 13:48:29', NULL, '2025-09-11 09:27:51', 7, 2, 'Inactive', '2025-09-10 19:18:29', '2025-09-11 14:57:51'),
(23, 'T-1-7-1-1756793223', 'acph_filter_1', '{\"average\": \"3.00\", \"readings\": {\"reading_1\": {\"value\": \"1\", \"instrument_id\": \"manual\"}, \"reading_2\": {\"value\": \"2\", \"instrument_id\": \"manual\"}, \"reading_3\": {\"value\": \"3\", \"instrument_id\": \"manual\"}, \"reading_4\": {\"value\": \"4\", \"instrument_id\": \"manual\"}, \"reading_5\": {\"value\": \"5\", \"instrument_id\": \"manual\"}}, \"cell_area\": \"4\", \"filter_id\": \"1\", \"flow_rate\": \"598\", \"filter_instrument\": \"manual\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-11 09:28:49', NULL, '2025-09-11 09:28:49', 7, 1, 'Active', '2025-09-11 14:58:49', '2025-09-11 14:58:49'),
(24, 'T-1-7-1-1756793223', 'acph_filter_2', '{\"average\": \"1.00\", \"readings\": {\"reading_1\": {\"value\": \"1\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"1\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"1\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"1\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"1\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"1\", \"filter_id\": \"2\", \"flow_rate\": \"1\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-09-11 09:29:05', NULL, '2025-09-11 09:29:05', 7, 2, 'Active', '2025-09-11 14:59:05', '2025-09-11 14:59:05'),
(25, 'T-1-7-1-1759219060', 'acph_filter_1', '{\"average\": \"0.00\", \"readings\": {\"reading_1\": {\"value\": \"0\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"0\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"0\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"0\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"0\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"5\", \"filter_id\": \"1\", \"flow_rate\": \"5\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"\", \"global_instrument\": \"INs234\", \"filter_instrument_mode\": \"\", \"global_instrument_mode\": \"single\"}', 74, '2025-10-07 13:40:59', NULL, '2025-10-07 13:40:59', 7, 1, 'Active', '2025-10-07 19:10:59', '2025-10-07 19:10:59'),
(26, 'T-1-7-1-1759219060', 'acph_filter_2', '{\"average\": \"15.00\", \"readings\": {\"reading_1\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"55\", \"instrument_id\": \"manual\"}, \"reading_3\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"55\", \"filter_id\": \"2\", \"flow_rate\": \"55\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"individual\", \"global_instrument_mode\": \"individual\"}', 74, '2025-10-07 16:26:44', NULL, '2025-10-07 16:26:44', 7, 2, 'Active', '2025-10-07 21:56:44', '2025-10-07 21:56:44'),
(27, 'T-1-7-1-1759907854', 'acph_filter_1', '{\"average\": \"12.00\", \"readings\": {\"reading_1\": {\"value\": \"12\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"12\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"12\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"12\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"12\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"12\", \"filter_id\": \"1\", \"flow_rate\": \"234\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-10-08 07:25:29', NULL, '2025-10-08 08:22:26', 7, 1, 'Inactive', '2025-10-08 12:55:29', '2025-10-08 13:52:26'),
(28, 'T-1-7-1-1759907854', 'acph_filter_2', '{\"average\": \"12.00\", \"readings\": {\"reading_1\": {\"value\": \"12\", \"instrument_id\": \"manual\"}, \"reading_2\": {\"value\": \"12\", \"instrument_id\": \"manual\"}, \"reading_3\": {\"value\": \"12\", \"instrument_id\": \"manual\"}, \"reading_4\": {\"value\": \"12\", \"instrument_id\": \"manual\"}, \"reading_5\": {\"value\": \"12\", \"instrument_id\": \"manual\"}}, \"cell_area\": \"12\", \"filter_id\": \"2\", \"flow_rate\": \"235\", \"filter_instrument\": \"\", \"global_instrument\": \"manual\", \"filter_instrument_mode\": \"\", \"global_instrument_mode\": \"single\"}', 74, '2025-10-08 07:26:53', NULL, '2025-10-08 08:22:26', 7, 2, 'Inactive', '2025-10-08 12:56:53', '2025-10-08 13:52:26'),
(29, 'T-3-7-1-1759911286', 'acph_filter_1', '{\"average\": \"5.00\", \"readings\": {\"reading_1\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"5\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"5\", \"filter_id\": \"1\", \"flow_rate\": \"5\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-10-08 09:27:33', NULL, '2025-10-08 09:27:33', 7, 1, 'Active', '2025-10-08 14:57:33', '2025-10-08 14:57:33'),
(30, 'T-1-7-1-1759907854', 'acph_filter_1', '{\"average\": \"65.80\", \"readings\": {\"reading_1\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"65\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"65\", \"filter_id\": \"1\", \"flow_rate\": \"5443\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-10-08 11:01:11', NULL, '2025-10-08 12:16:52', 7, 1, 'Inactive', '2025-10-08 16:31:11', '2025-10-08 17:46:52'),
(31, 'T-1-7-1-1759907854', 'acph_filter_2', '{\"average\": \"66.00\", \"readings\": {\"reading_1\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"66\", \"filter_id\": \"2\", \"flow_rate\": \"6544\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-10-08 11:01:19', NULL, '2025-10-08 12:16:58', 7, 2, 'Inactive', '2025-10-08 16:31:19', '2025-10-08 17:46:58'),
(32, 'T-1-7-1-1759907854', 'acph_filter_1', '{\"average\": \"65.80\", \"readings\": {\"reading_1\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"65\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"67\", \"filter_id\": \"1\", \"flow_rate\": \"5443\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-10-08 12:16:52', NULL, '2025-10-08 12:19:02', 7, 1, 'Inactive', '2025-10-08 17:46:52', '2025-10-08 17:49:02'),
(33, 'T-1-7-1-1759907854', 'acph_filter_2', '{\"average\": \"66.00\", \"readings\": {\"reading_1\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"66\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"67\", \"filter_id\": \"2\", \"flow_rate\": \"6544\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-10-08 12:16:58', NULL, '2025-10-08 12:19:02', 7, 2, 'Inactive', '2025-10-08 17:46:58', '2025-10-08 17:49:02'),
(34, 'T-1-7-1-1759907854', 'acph_filter_1', '{\"average\": \"32.40\", \"readings\": {\"reading_1\": {\"value\": \"33\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"32\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"32\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"33\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"32\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"32\", \"filter_id\": \"1\", \"flow_rate\": \"31.99\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"\", \"global_instrument\": \"INs234\", \"filter_instrument_mode\": \"\", \"global_instrument_mode\": \"single\"}', 74, '2025-10-08 12:28:42', NULL, '2025-10-08 12:32:03', 7, 1, 'Inactive', '2025-10-08 17:58:42', '2025-10-08 18:02:03'),
(35, 'T-1-7-1-1759907854', 'acph_filter_2', '{\"average\": \"33.00\", \"readings\": {\"reading_1\": {\"value\": \"33\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"33\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"33\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"33\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"33\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"33\", \"filter_id\": \"2\", \"flow_rate\": \"32.98\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"\", \"global_instrument\": \"INs234\", \"filter_instrument_mode\": \"\", \"global_instrument_mode\": \"single\"}', 74, '2025-10-08 12:28:55', NULL, '2025-10-08 12:32:03', 7, 2, 'Inactive', '2025-10-08 17:58:55', '2025-10-08 18:02:03'),
(36, 'T-1-7-1-1759907854', 'acph_filter_1', '{\"average\": \"23.00\", \"readings\": {\"reading_1\": {\"value\": \"23\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"23\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"23\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"23\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"23\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"23\", \"filter_id\": \"1\", \"flow_rate\": \"23\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-10-08 12:33:14', NULL, '2025-10-08 12:33:14', 7, 1, 'Active', '2025-10-08 18:03:14', '2025-10-08 18:03:14'),
(37, 'T-1-7-1-1759907854', 'acph_filter_2', '{\"average\": \"24.60\", \"readings\": {\"reading_1\": {\"value\": \"24\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"24\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"25\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"25\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"25\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"25\", \"filter_id\": \"2\", \"flow_rate\": \"56\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-10-08 12:33:36', NULL, '2025-10-08 12:33:36', 7, 2, 'Active', '2025-10-08 18:03:36', '2025-10-08 18:03:36'),
(38, 'T-4-7-1-1759946634', 'acph_filter_1', '{\"average\": \"5.00\", \"readings\": {\"reading_1\": {\"value\": \"5\", \"instrument_id\": \"manual\"}, \"reading_2\": {\"value\": \"5\", \"instrument_id\": \"manual\"}, \"reading_3\": {\"value\": \"5\", \"instrument_id\": \"manual\"}, \"reading_4\": {\"value\": \"5\", \"instrument_id\": \"manual\"}, \"reading_5\": {\"value\": \"5\", \"instrument_id\": \"manual\"}}, \"cell_area\": \"5\", \"filter_id\": \"1\", \"flow_rate\": \"5\", \"filter_instrument\": \"\", \"global_instrument\": \"manual\", \"filter_instrument_mode\": \"\", \"global_instrument_mode\": \"single\"}', 74, '2025-10-08 18:09:51', NULL, '2025-10-08 18:09:51', 7, 1, 'Active', '2025-10-08 23:39:51', '2025-10-08 23:39:51'),
(39, 'T-1-7-1-1760164349', 'acph_filter_1', '{\"average\": \"52.00\", \"readings\": {\"reading_1\": {\"value\": \"56\", \"instrument_id\": \"INST003\"}, \"reading_2\": {\"value\": \"54\", \"instrument_id\": \"INST003\"}, \"reading_3\": {\"value\": \"53\", \"instrument_id\": \"INST003\"}, \"reading_4\": {\"value\": \"54\", \"instrument_id\": \"INST003\"}, \"reading_5\": {\"value\": \"43\", \"instrument_id\": \"INST003\"}}, \"cell_area\": \"45\", \"filter_id\": \"1\", \"flow_rate\": \"55\", \"instruments_used\": [\"INST003\"], \"filter_instrument\": \"INST003\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-10-11 07:11:57', NULL, '2025-10-11 07:11:57', 7, 1, 'Active', '2025-10-11 12:41:57', '2025-10-11 12:41:57'),
(40, 'T-1-7-1-1760164349', 'acph_filter_2', '{\"average\": \"46.40\", \"readings\": {\"reading_1\": {\"value\": \"55\", \"instrument_id\": \"INs234\"}, \"reading_2\": {\"value\": \"55\", \"instrument_id\": \"INs234\"}, \"reading_3\": {\"value\": \"54\", \"instrument_id\": \"INs234\"}, \"reading_4\": {\"value\": \"34\", \"instrument_id\": \"INs234\"}, \"reading_5\": {\"value\": \"34\", \"instrument_id\": \"INs234\"}}, \"cell_area\": \"55\", \"filter_id\": \"2\", \"flow_rate\": \"355\", \"instruments_used\": [\"INs234\"], \"filter_instrument\": \"INs234\", \"global_instrument\": \"\", \"filter_instrument_mode\": \"single\", \"global_instrument_mode\": \"individual\"}', 74, '2025-10-11 07:12:26', NULL, '2025-10-11 07:12:26', 7, 2, 'Active', '2025-10-11 12:42:26', '2025-10-11 12:42:26');

-- --------------------------------------------------------

--
-- Table structure for table `trigger_error_log`
--

CREATE TABLE `trigger_error_log` (
  `error_id` int NOT NULL,
  `trigger_name` varchar(50) NOT NULL,
  `error_timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `test_id` int DEFAULT NULL,
  `error_message` text,
  `sql_state` varchar(10) DEFAULT NULL,
  `error_code` int DEFAULT NULL,
  `test_conducted_date` date DEFAULT NULL,
  `planned_execution_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `trigger_error_log`
--

INSERT INTO `trigger_error_log` (`error_id`, `trigger_name`, `error_timestamp`, `table_name`, `record_id`, `test_id`, `error_message`, `sql_state`, `error_code`, `test_conducted_date`, `planned_execution_date`) VALUES
(2, 'tr_validation_auto_schedule', '2025-07-29 13:33:02', 'tbl_test_schedules_tracking', 9999, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-11-14', NULL),
(3, 'tr_auto_schedule_test_completion', '2025-07-29 13:33:40', 'tbl_test_schedules_tracking', 9998, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2026-01-14', NULL),
(4, 'tr_validation_auto_schedule', '2025-07-29 13:33:40', 'tbl_test_schedules_tracking', 9998, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2026-01-14', NULL),
(5, 'tr_auto_schedule_test_completion', '2025-07-29 13:33:40', 'tbl_test_schedules_tracking', 9997, 1, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2024-09-14', NULL),
(6, 'tr_routine_test_auto_schedule', '2025-07-29 13:33:40', 'tbl_test_schedules_tracking', 9997, 1, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2024-09-14', NULL),
(7, 'tr_auto_schedule_test_completion', '2025-07-29 13:33:40', 'tbl_test_schedules_tracking', 9996, 1, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-02-25', NULL),
(8, 'tr_routine_test_auto_schedule', '2025-07-29 13:33:40', 'tbl_test_schedules_tracking', 9996, 1, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-02-25', NULL),
(9, 'tr_auto_schedule_test_completion', '2025-07-29 13:33:40', 'tbl_test_schedules_tracking', 9995, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-11-14', NULL),
(10, 'tr_validation_auto_schedule', '2025-07-29 13:33:40', 'tbl_test_schedules_tracking', 9995, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-11-14', NULL),
(11, 'tr_auto_schedule_test_completion', '2025-07-29 13:46:28', 'tbl_test_schedules_tracking', 8888, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-11-14', NULL),
(12, 'tr_validation_auto_schedule', '2025-07-29 13:46:28', 'tbl_test_schedules_tracking', 8888, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-11-14', NULL),
(13, 'tr_auto_schedule_test_completion', '2025-07-29 13:57:48', 'tbl_test_schedules_tracking', 7001, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-11-14', NULL),
(14, 'tr_validation_auto_schedule', '2025-07-29 13:57:48', 'tbl_test_schedules_tracking', 7001, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-11-14', NULL),
(15, 'tr_auto_schedule_test_completion', '2025-07-29 13:57:48', 'tbl_test_schedules_tracking', 7002, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-11-14', NULL),
(16, 'tr_validation_auto_schedule', '2025-07-29 13:57:48', 'tbl_test_schedules_tracking', 7002, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-11-14', NULL),
(17, 'tr_auto_schedule_test_completion', '2025-07-29 13:57:48', 'tbl_test_schedules_tracking', 7003, 1, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-03-10', NULL),
(18, 'tr_routine_test_auto_schedule', '2025-07-29 13:57:48', 'tbl_test_schedules_tracking', 7003, 1, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-03-10', NULL),
(19, 'tr_auto_schedule_test_completion', '2025-07-29 13:57:48', 'tbl_test_schedules_tracking', 7004, 2, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-01-20', NULL),
(20, 'tr_routine_test_auto_schedule', '2025-07-29 13:57:48', 'tbl_test_schedules_tracking', 7004, 2, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-01-20', NULL),
(21, 'tr_auto_schedule_test_completion', '2025-07-29 13:57:48', 'tbl_test_schedules_tracking', 7005, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-11-14', NULL),
(22, 'tr_validation_auto_schedule', '2025-07-29 13:57:48', 'tbl_test_schedules_tracking', 7005, 4, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-11-14', NULL),
(23, 'tr_auto_schedule_test_completion', '2025-07-29 18:41:53', 'tbl_test_schedules_tracking', 11530, 9, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-07-29', NULL),
(24, 'tr_validation_auto_schedule', '2025-07-29 18:41:53', 'tbl_test_schedules_tracking', 11530, 9, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-07-29', NULL),
(25, 'tr_auto_schedule_test_completion', '2025-07-29 19:43:18', 'tbl_test_schedules_tracking', 11529, 3, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-07-29', NULL),
(26, 'tr_validation_auto_schedule', '2025-07-29 19:43:18', 'tbl_test_schedules_tracking', 11529, 3, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-07-29', NULL),
(27, 'tr_auto_schedule_test_completion', '2025-08-10 11:19:32', 'tbl_test_schedules_tracking', 11535, 6, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-01-16', NULL),
(28, 'tr_auto_schedule_test_completion', '2025-08-10 11:34:54', 'tbl_test_schedules_tracking', 11536, 6, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-08-10', NULL),
(29, 'tr_auto_schedule_test_completion', '2025-08-10 20:15:00', 'tbl_test_schedules_tracking', 11537, 6, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-08-10', NULL),
(30, 'tr_auto_schedule_test_completion', '2025-08-10 20:24:57', 'tbl_test_schedules_tracking', 11538, 6, 'Can\'t update table \'tbl_test_schedules_tracking\' in stored function/trigger because it is already used by statement which invoked this stored function/trigger.', NULL, NULL, '2025-08-10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `unit_id` int NOT NULL,
  `unit_name` varchar(100) DEFAULT NULL,
  `unit_site` varchar(100) DEFAULT 'Goa',
  `unit_status` varchar(45) DEFAULT 'Active',
  `unit_creation_datetime` datetime DEFAULT NULL,
  `unit_last_modification_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `primary_test_id` int DEFAULT NULL,
  `secondary_test_id` int DEFAULT NULL,
  `two_factor_enabled` enum('Yes','No') NOT NULL DEFAULT 'No' COMMENT 'Enable/disable 2FA for this unit',
  `otp_validity_minutes` int NOT NULL DEFAULT '5' COMMENT 'OTP validity period in minutes (1-15)',
  `otp_digits` int NOT NULL DEFAULT '6' COMMENT 'Number of digits in OTP (4-8)',
  `otp_resend_delay_seconds` int NOT NULL DEFAULT '60' COMMENT 'Delay between OTP resend requests',
  `validation_scheduling_logic` enum('dynamic','fixed') NOT NULL DEFAULT 'dynamic' COMMENT 'Validation scheduling logic: dynamic dates adjust automatically based on last validation, fixed dates remain \n  constant from initial setup'
) ;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`unit_id`, `unit_name`, `unit_site`, `unit_status`, `unit_creation_datetime`, `unit_last_modification_datetime`, `primary_test_id`, `secondary_test_id`, `two_factor_enabled`, `otp_validity_minutes`, `otp_digits`, `otp_resend_delay_seconds`, `validation_scheduling_logic`) VALUES
(7, 'Unit VII', 'Goa', 'Active', '2020-09-13 13:47:37', '2025-09-17 00:19:36', 1, NULL, 'No', 5, 6, 60, 'fixed'),
(8, 'Unit VIII', 'Goa', 'Active', '2020-09-13 13:47:37', '2020-09-13 13:47:37', 1, NULL, 'No', 5, 6, 60, 'dynamic'),
(15, 'test', 'Goa', 'Active', '2025-08-30 20:06:03', '2025-09-14 20:03:35', 20, NULL, 'No', 5, 6, 60, 'dynamic'),
(16, 'test Unit', 'Goa', 'Inactive', '2025-08-30 19:47:39', '2025-09-01 19:31:31', 20, NULL, 'No', 5, 6, 60, 'dynamic'),
(72, 'Unit VIIPDII', 'Goa', 'Active', '2025-04-10 06:57:28', '2025-04-10 06:57:28', 1, NULL, 'No', 5, 6, 60, 'dynamic');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `employee_id` varchar(45) DEFAULT NULL,
  `user_type` varchar(45) DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `user_name` varchar(200) DEFAULT NULL,
  `user_mobile` varchar(45) DEFAULT NULL,
  `user_email` varchar(100) DEFAULT NULL,
  `unit_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `is_qa_head` varchar(45) DEFAULT 'No',
  `is_unit_head` varchar(45) DEFAULT 'No',
  `is_admin` varchar(45) DEFAULT 'No',
  `is_super_admin` varchar(45) DEFAULT 'No',
  `is_dept_head` varchar(45) DEFAULT 'No',
  `user_domain_id` varchar(100) DEFAULT NULL,
  `user_status` enum('Active','Inactive','Pending') NOT NULL DEFAULT 'Active',
  `user_created_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `user_last_modification_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `user_password` varchar(500) DEFAULT NULL,
  `is_account_locked` varchar(45) DEFAULT NULL,
  `is_default_password` varchar(45) DEFAULT 'Yes',
  `submitted_by` int DEFAULT NULL COMMENT 'User ID who submitted/modified the record',
  `checker_id` int DEFAULT NULL COMMENT 'User ID who performed checker approval/rejection',
  `checker_action` enum('Approved','Rejected') DEFAULT NULL COMMENT 'Checker decision',
  `checker_date` datetime DEFAULT NULL COMMENT 'Date and time of checker action',
  `checker_remarks` text COMMENT 'Checker comments/remarks',
  `original_data` json DEFAULT NULL COMMENT 'Original data before modification for audit trail'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `employee_id`, `user_type`, `vendor_id`, `user_name`, `user_mobile`, `user_email`, `unit_id`, `department_id`, `is_qa_head`, `is_unit_head`, `is_admin`, `is_super_admin`, `is_dept_head`, `user_domain_id`, `user_status`, `user_created_datetime`, `user_last_modification_datetime`, `user_password`, `is_account_locked`, `is_default_password`, `submitted_by`, `checker_id`, `checker_action`, `checker_date`, `checker_remarks`, `original_data`) VALUES
(41, '23188', 'employee', 0, 'QA Head One', '9876543210', 'omkar@palcoa.com', 7, 9, 'Yes', 'No', 'Yes', 'No', 'No', 'qa_head_one', 'Active', '2021-07-27 10:28:03', '2025-08-31 00:00:06', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(42, '111838', 'employee', 0, 'Engg User One', '9876543210', 'omkar@palcoa.com', 7, 1, 'No', 'No', 'No', 'No', 'No', 'engg_user_one', 'Active', '2021-07-27 10:29:18', '2025-06-28 01:35:19', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(44, '23014', 'employee', 0, 'Engg Head One', '9876543210', 'omkar@palcoa.com', 7, 1, 'No', 'No', 'No', 'No', 'Yes', 'engg_head_one', 'Active', '2021-07-27 10:32:49', '2025-06-16 20:42:56', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(46, '93269', 'employee', 0, 'QA User One', '9876543210', 'omkar@palcoa.com', 7, 8, 'No', 'No', 'No', 'No', 'No', 'qa_user_one', 'Active', '2021-08-04 14:30:28', '2021-08-04 14:30:28', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(47, '64193', 'employee', 0, 'Topical User One', '9876543210', 'omkar@palcoa.com', 7, 5, 'No', 'No', 'No', 'No', 'No', 'top_user_one', 'Active', '2021-08-04 14:31:48', '2021-10-14 12:26:19', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(48, '23122', 'employee', 0, 'Packing User One', '9876543210', 'omkar@palcoa.com', 7, 3, 'No', 'No', 'No', 'No', 'No', 'pack_user_one', 'Active', '2021-08-04 14:33:11', '2021-08-04 14:33:11', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(49, '129370', 'employee', 0, 'Engg User Two', '9876543210', 'omkar@palcoa.com', 7, 1, 'No', 'No', 'No', 'No', 'No', 'engg_user_two', 'Active', '2021-08-04 14:34:35', '2022-11-14 10:50:37', 'palcoa123', 'Yes', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(50, '146266', 'employee', 0, 'EHS User One', '9876543210', 'omkar@palcoa.com', 7, 7, 'No', 'No', 'No', 'No', 'No', 'ehs_user_one', 'Active', '2021-08-04 14:35:51', '2025-04-24 17:54:41', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(51, '21864', 'employee', 0, 'Stores User One', '9876543210', 'omkar@palcoa.com', 7, 4, 'No', 'No', 'No', 'No', 'No', 'stores_user_one', 'Active', '2021-08-04 14:37:08', '2021-12-17 11:37:38', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(52, '110797', 'employee', 0, 'EHS User Two', '9876543210', 'omkar@palcoa.com', 7, 7, 'No', 'No', 'No', 'No', 'No', 'ehs_user_two', 'Active', '2021-08-09 15:16:17', '2023-02-20 17:37:11', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(53, '129387', 'employee', 0, 'QC User One', '9876543210', 'omkar@palcoa.com', 7, 0, 'No', 'No', 'No', 'No', 'No', 'qc_user_one', 'Active', '2021-08-09 15:19:44', '2024-06-22 15:59:26', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(54, '22648', 'employee', 0, 'Unit Head One', '9876543210', 'omkar@palcoa.com', 7, 9, 'No', 'Yes', 'No', 'No', 'No', 'unit_head_one', 'Active', '2021-08-20 11:14:56', '2021-08-20 11:14:56', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(56, '14425', 'employee', 0, 'QA User Two', '9876543210', 'omkar@palcoa.com', 7, 8, 'No', 'No', 'No', 'No', 'No', 'qa_user_two', 'Active', '2021-09-17 16:47:22', '2021-09-17 16:47:22', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(58, '118045', 'employee', 0, 'Micro User One', '9876543210', 'omkar@palcoa.com', 7, 6, 'No', 'No', 'No', 'No', 'No', 'micro_user_one', 'Active', '2021-09-18 16:17:38', '2024-09-17 14:41:23', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(59, '15577', 'employee', 0, 'Prod User One', '9876543210', 'omkar@palcoa.com', 7, 2, 'No', 'No', 'No', 'No', 'No', 'prod_user_one', 'Active', '2021-09-22 13:16:57', '2021-09-22 13:16:57', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(60, '33503', 'employee', 0, 'QA Head Two', '9876543210', 'omkar@palcoa.com', 7, 8, 'Yes', 'No', 'No', 'No', 'No', 'qa_head_two', 'Active', '2021-10-04 12:56:42', '2021-10-04 12:56:42', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(61, '103017', 'employee', 0, 'Prod User Two', '9876543210', 'omkar@palcoa.com', 7, 2, 'No', 'No', 'No', 'No', 'No', 'prod_user_two', 'Active', '2021-10-28 16:48:05', '2021-10-28 16:48:05', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(63, '118086', 'employee', 0, 'QC User Two', '9876543210', 'omkar@palcoa.com', 7, 0, 'No', 'No', 'No', 'No', 'No', 'qc_user_two', 'Active', '2021-11-11 11:36:36', '2022-11-14 10:52:23', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(64, '110680', 'employee', 0, 'Topical User Two', '9876543210', 'omkar@palcoa.com', 7, 5, 'No', 'No', 'No', 'No', 'No', 'top_user_two', 'Active', '2021-11-11 11:39:39', '2021-11-11 11:39:39', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(67, '23006', 'employee', 0, 'Unit Head Two', '9876543210', 'omkar@palcoa.com', 7, 9, 'No', 'Yes', 'No', 'No', 'No', 'unit_head_two', 'Active', '2021-12-11 15:08:08', '2022-11-14 10:52:05', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(69, '116134', 'employee', 0, 'Stores User Two', '9876543210', 'omkar@palcoa.com', 7, 4, 'No', 'No', 'No', 'No', 'No', 'stores_user_two', 'Active', '2022-01-08 07:51:50', '2022-01-08 07:51:50', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(74, '1', 'vendor', 3, 'Vendor User One I', '9876543210', 'omkar@palcoa.com', NULL, NULL, 'No', 'No', 'No', 'No', 'No', 'vendor_user_one', 'Active', '2022-02-25 17:17:20', '2025-08-31 00:01:51', 'palcoa123', 'No', 'No', 41, NULL, NULL, NULL, NULL, NULL),
(88, '2', 'vendor', 3, 'Vendor User Two', '9876543210', 'omkar@palcoa.com', NULL, NULL, 'No', 'No', 'No', 'No', 'No', 'vendor_user_two', 'Active', '2022-07-01 15:09:42', '2025-09-12 12:21:26', 'palcoa123', 'No', 'No', 41, NULL, NULL, NULL, NULL, NULL),
(121, '23041', 'employee', 0, 'Packing User Two', '9876543210', 'omkar@palcoa.com', 7, 3, 'No', 'No', 'No', 'No', 'Yes', 'pack_user_two', 'Active', '2022-09-29 12:57:18', '2024-09-20 15:16:45', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(2039, '22419', 'employee', 0, 'Micro User Two', '9876543210', 'omkar@palcoa.com', 7, 6, 'No', 'No', 'No', 'No', 'No', 'micro_user_two', 'Active', '2024-07-24 10:21:33', '2024-07-24 10:21:33', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(2050, '15600027', 'employee', 0, 'IT User One', '9876543210', 'omkar@palcoa.com', 7, 11, 'No', 'No', 'No', 'Yes', 'No', 'it_user_one', 'Active', '2024-11-21 17:13:31', '2025-08-31 03:32:55', 'palcoa123', 'No', 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(3057, '123', 'vendor', 1, 'Omkar', '9819316186', 'omkar@palcoa.com', NULL, NULL, 'No', 'No', 'No', 'No', 'No', 'test.vendor', 'Active', '2025-04-10 14:32:56', '2025-06-15 19:35:30', '$2y$12$K.1ZE7BYZZ2rRyS4pciHqe0zf5K/0Yn7Qh..eyL3nKaFGjO7S/Rea', 'No', 'Yes', 41, NULL, NULL, NULL, NULL, NULL),
(3058, 'test', 'vendor', 1, 'test', '9819316186', 'omkar@palcoa.com', NULL, NULL, 'No', 'No', 'No', 'No', 'No', NULL, 'Active', '2025-04-24 17:57:28', '2025-04-24 17:57:28', NULL, NULL, 'No', 41, NULL, NULL, NULL, NULL, NULL),
(3059, 'test2', 'vendor', 1, 'omkar', '9819316186', 'omkar@palcoa.com', NULL, NULL, 'No', 'No', 'No', 'No', 'No', 'om.palcoa', 'Active', '2025-04-24 20:03:05', '2025-04-24 20:03:05', 'palcoa123', NULL, 'No', 41, NULL, NULL, NULL, NULL, NULL),
(3060, '3960636', 'vendor', 4, 'omkar', '9819316186', 'omkar@palcoa.com', NULL, NULL, 'No', 'No', 'No', 'No', 'No', 'ompatil', 'Active', '2025-05-26 19:28:13', '2025-05-26 19:28:13', NULL, NULL, 'No', 41, NULL, NULL, NULL, NULL, NULL),
(3061, 'testing', 'employee', 0, 'testing', '9819316186', 'omkar@palcoa.com', 8, 1, 'No', 'No', 'No', 'No', 'Yes', 'omkar4.patil', 'Active', '2025-05-26 19:29:58', '2025-05-26 19:29:58', NULL, NULL, 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(3062, 'A1234', 'employee', 0, 'omkar.patil', '1234567890', 'omkar@palcoa.com', 7, 0, 'No', 'No', 'No', 'No', 'No', 'omkar.vendor', 'Active', '2025-06-15 19:44:29', '2025-06-15 19:44:29', NULL, NULL, 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(3063, 'A1234', 'vendor', 1, 'Vishnu', '9819316186', 'omkar@palcoa.com', NULL, NULL, 'No', 'No', 'No', 'No', 'No', 'vishnu.vendor', 'Active', '2025-06-15 19:45:41', '2025-06-15 19:45:41', NULL, NULL, 'No', 41, NULL, NULL, NULL, NULL, NULL),
(3064, '3245', 'employee', 0, 'Test', '9819316186', 'omkar@palcoa.com', 7, 11, 'No', 'No', 'Yes', 'No', 'No', 'omk.test', 'Active', '2025-08-13 10:08:40', '2025-08-13 10:08:40', NULL, NULL, 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(3065, '564', 'employee', 0, 'omk', '9819316186', 'omkar@palcoa.com', 7, 11, 'No', 'No', 'Yes', 'No', 'No', 'omk.test2', 'Active', '2025-08-13 10:14:48', '2025-08-13 10:14:48', NULL, NULL, 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(3066, '8765', 'employee', 0, 'om', '9876543212', 'omkar@palcoa.com', 7, 11, 'No', 'No', 'Yes', 'No', 'No', 'om.t456', 'Active', '2025-08-13 10:16:01', '2025-08-13 10:16:01', NULL, NULL, 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(3067, '123', 'employee', 0, 'om', '9876543210', 'omkar@palcoa.com', 7, 11, 'No', 'No', 'Yes', 'No', 'No', 'om.test', 'Active', '2025-08-13 10:21:15', '2025-08-13 10:21:15', NULL, NULL, 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(3068, '111', 'employee', 0, 'om', '9819316186', 'omkar@palcoa.com', 7, 11, 'No', 'No', 'Yes', 'No', 'No', 'om.tes', 'Active', '2025-08-13 10:32:58', '2025-08-13 10:32:58', NULL, NULL, 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(3069, '18765', 'employee', 0, 'o', '9819316186', 'omkar@palcoa.com', 7, 11, 'No', 'No', 'Yes', 'No', 'No', 'o.j', 'Active', '2025-08-13 10:35:51', '2025-08-13 10:35:51', NULL, NULL, 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(3070, '23fn', 'employee', 0, 'om', '9819316186', 'omkar@palcoa.com', 7, 11, 'No', 'No', 'Yes', 'No', 'No', 'om.tech', 'Active', '2025-08-13 11:39:24', '2025-08-13 11:39:24', NULL, NULL, 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(3071, '1234321', 'employee', 0, 'test', '9819316186', 'omkar@palcoa.com', 7, 1, 'No', 'No', 'No', 'No', 'Yes', 't.a', 'Active', '2025-08-31 00:09:45', '2025-08-31 00:09:45', NULL, NULL, 'Yes', NULL, NULL, NULL, NULL, NULL, NULL),
(3072, '1986', 'vendor', 1, 'test', '9819361618', 'omkar@palcoa.com', NULL, NULL, 'No', 'No', 'No', 'No', 'No', '1986.om', 'Active', '2025-08-31 00:11:52', '2025-08-31 00:11:52', NULL, NULL, 'No', 41, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_otp_sessions`
--

CREATE TABLE `user_otp_sessions` (
  `otp_session_id` int NOT NULL,
  `user_id` int NOT NULL,
  `unit_id` int NOT NULL,
  `employee_id` varchar(50) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `is_used` enum('Yes','No') NOT NULL DEFAULT 'No',
  `attempts_count` int NOT NULL DEFAULT '0',
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `session_token` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Stores OTP sessions for two-factor authentication';

--
-- Dumping data for table `user_otp_sessions`
--

INSERT INTO `user_otp_sessions` (`otp_session_id`, `user_id`, `unit_id`, `employee_id`, `otp_code`, `created_at`, `expires_at`, `is_used`, `attempts_count`, `ip_address`, `user_agent`, `session_token`) VALUES
(1, 42, 7, '111838', '459793', '2025-08-30 07:32:36', '2025-08-30 07:37:36', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'a9caa8b1a2bbec218a223a93c7851e0bc406547f79fb57dfa2337788c6bcd8d5'),
(2, 42, 7, '111838', '361163', '2025-08-30 07:35:55', '2025-08-30 07:40:55', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '6895a31747fd9f65f4bb1f957213d5570acd4951ac80458ab5a36e15d0918acc'),
(3, 42, 7, '111838', '030949', '2025-08-30 07:43:00', '2025-08-30 07:48:00', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '243c775d644e406e5c9a971fa89272e79eb348c1cf4569de8600fe09bdf684e0'),
(4, 42, 7, '111838', '668093', '2025-08-30 07:44:30', '2025-08-30 07:49:30', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '18b801a69d805d0d22c40168f7d0697a18dcd907275628747bb7a4af5d94d083'),
(5, 42, 7, '111838', '451388', '2025-08-30 07:47:02', '2025-08-30 07:52:02', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:142.0) Gecko/20100101 Firefox/142.0', '90389ee8249ace347e101d90efa7162f9fc9f54f25f0b661a09b0da97162c50b'),
(6, 42, 7, '111838', '661875', '2025-08-30 07:48:34', '2025-08-30 07:53:34', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '0e82496b7a3c4a59f6c34785c7a16bc49958681e7efb58690fcb589bde40fa67'),
(7, 42, 7, '111838', '514874', '2025-08-30 07:49:30', '2025-08-30 07:54:30', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '49de8a81a59ebdae50c0c1c264185e956c06f12794094eee41fe6933628655aa'),
(8, 42, 7, '111838', '235399', '2025-08-30 07:55:16', '2025-08-30 08:00:16', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'fff3967210d14145fb642ce6127076c7f536fa35f64d8c391832286f1a117d34'),
(9, 42, 7, '111838', '196020', '2025-08-30 07:56:10', '2025-08-30 08:01:10', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '7c4fe2170e84ae1af1ca00a85886e7ba8825e1572274804b52167a6baf276abc'),
(10, 42, 7, '111838', '743684', '2025-08-30 07:57:13', '2025-08-30 08:02:13', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '3e70b7ed9b45bfebdae1bf48e23c006cbfb82bd14f11a567d2aa318dcf9422e1'),
(11, 42, 7, '111838', '135216', '2025-08-30 07:57:32', '2025-08-30 08:02:32', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'ee02adb552a4447e2d64922e182407c79266f4132cb2c0e014994f47671b8f01'),
(12, 42, 7, '111838', '761599', '2025-08-30 07:57:48', '2025-08-30 08:02:48', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '701803c2911469199a4df49adcf794e82337c8388d5535cb9a0173741de2633c'),
(13, 42, 7, '111838', '365186', '2025-08-30 07:59:36', '2025-08-30 08:04:36', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'e2ea3ec74f360deb9d80fd218e2f2bfe98f5b589e3484d1d98af370c786cd8da'),
(14, 42, 7, '111838', '452377', '2025-08-30 08:00:13', '2025-08-30 08:05:13', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'c328e6a61b6d2b8ecbf7d6c688bef18e4206b2bdd94dd91bdbef15b13f56455e'),
(15, 42, 7, '111838', '183808', '2025-08-30 08:01:05', '2025-08-30 08:06:05', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'd178662948184857bd434bed1373cce915e1baba2894cad03771ae1584034c4e'),
(16, 42, 7, '111838', '700971', '2025-08-30 08:01:41', '2025-08-30 08:06:41', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'c94adf178b594f6e85ef971689dc5098221fe74961d1fe7e7db435b0c9182b61'),
(17, 42, 7, '111838', '535817', '2025-08-30 08:03:38', '2025-08-30 08:08:38', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '321117f9fc304dea40c4baf154ab1ed1094bbb9ca63ac74f27eb76c82489a02d'),
(18, 42, 7, '111838', '289807', '2025-08-30 08:05:34', '2025-08-30 08:10:34', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '1a8b80b3618199381b56af12ab9e6f747eed6cb04dfe1d93f182496fdeebbfc3'),
(19, 42, 7, '111838', '020989', '2025-08-30 08:06:35', '2025-08-30 08:11:35', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '5ddc8bbdb60c4d4bb25dd75ce64709f4e558869bc17a2962ec1e1e11adf0ca4a'),
(20, 42, 7, '111838', '337667', '2025-08-30 08:13:56', '2025-08-30 08:18:56', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '143d146f8c344452cc75979b3751dabf906192216f88ff012b4dc584f82845e1'),
(21, 42, 7, '111838', '025893', '2025-08-30 08:15:05', '2025-08-30 08:20:05', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'f55438f71e18b41040e1378b95750023250901b4f85c1af1be659b1f3fb189f9'),
(22, 42, 7, '111838', '553253', '2025-08-30 08:28:22', '2025-08-30 08:33:22', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'd1d91a62b47cd73a19c8a83e5cb0db11d8719555b5817628946055e0869b1bad'),
(23, 42, 7, '111838', '337055', '2025-08-30 08:39:16', '2025-08-30 08:44:16', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '8ec33309c121167b928b2e9b589ef41d17dfce327a48c2c9601ac30e33da5598'),
(24, 42, 7, '111838', '906114', '2025-08-30 08:40:21', '2025-08-30 08:45:21', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '9a674a06f5401686324f4f61c4868df3011df0b924c4e33f9bafb8b5ff76a960'),
(25, 42, 7, '111838', '413490', '2025-08-30 08:41:45', '2025-08-30 08:46:45', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'e004b7c88ecd110c7bbfb1d50b666c0d3d15b160b8140afa867472d61423cbbf'),
(26, 42, 7, '111838', '254911', '2025-08-30 08:46:37', '2025-08-30 08:51:37', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '50f23bb2d697d465ce3ce9207fa3e14b959b3dd0e609e537e99368d606b8d000'),
(27, 42, 7, '111838', '863863', '2025-08-30 08:50:58', '2025-08-30 08:55:58', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'a6fb1429ea8cbdf14cc22ed80b304087981e6d243e65db566e1b66d9213325cf'),
(28, 42, 7, '111838', '135222', '2025-08-30 08:52:37', '2025-08-30 08:57:37', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '095e52645d33a5c252ef51c495e7335977b3c7c175570fd64d2907c78904c579'),
(29, 42, 7, '111838', '868545', '2025-08-30 08:52:46', '2025-08-30 08:57:46', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '56da87ce2cc17b37474ebd5dd196b79ee19833236bb6881c95b76e7ad573979a'),
(30, 42, 7, '111838', '126137', '2025-08-30 08:57:31', '2025-08-30 09:02:31', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '46f60f68a6fca0e1300fad76626dc79fc8ce719643fbe3433e1d1e1d370e335c'),
(31, 42, 7, '111838', '699658', '2025-08-30 09:01:36', '2025-08-30 09:06:36', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '6137871eaf960f3e8498344844f7247ba79188fc6bc902d71e159703d98e41a7'),
(32, 42, 7, '111838', '126955', '2025-08-30 09:02:06', '2025-08-30 09:07:06', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '420347c1c755e12eceb98a2c0dd243b765f3845f8bf6ea8f28131e66af80c02d'),
(33, 42, 7, '111838', '681940', '2025-08-30 09:09:29', '2025-08-30 09:14:29', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '091be629f3a9611850e2f907ee52220c5308b9c1eabe820e7023f0c8325fbb2b'),
(34, 42, 7, '111838', '521215', '2025-08-30 09:09:48', '2025-08-30 09:14:48', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'f6dd58af22e1efdc89b5dc24b64cce467ac184bbbc9204deefe0bd3e8655d142'),
(35, 42, 7, '111838', '574471', '2025-08-30 09:33:41', '2025-08-30 09:38:41', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '9adfb18988d64dec696ca43ad2fb388503e76b84242f5ac0cab5ce1adf93fe42'),
(36, 42, 7, '111838', '558234', '2025-08-30 09:36:40', '2025-08-30 09:41:40', 'Yes', 2, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '9499fd7080cd19df7c4e662447c48fc5f508b67e3ad70fe914277967540a88d9'),
(37, 42, 7, '111838', '984288', '2025-08-30 09:44:45', '2025-08-30 09:49:45', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '3c1cbcbec65a69eccb1cdd9c4f55084f7c5dffc9e1aeea87fb117e9b632c90cf'),
(38, 42, 7, '111838', '232862', '2025-08-30 09:45:20', '2025-08-30 09:50:20', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'f21882904b54fb34f995d1063abb0799045ca71cd2d80c17b5ad4c63a53ac5b1'),
(39, 42, 7, '111838', '796736', '2025-08-30 09:48:53', '2025-08-30 09:53:53', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'bb60027d4cdde9c977cd1a785087a89a18e477b2784bda5252749c08748bd7d7'),
(40, 42, 7, '111838', '213904', '2025-08-30 10:08:39', '2025-08-30 10:13:39', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'e18fe4dd55972836246d9aade809abb91bdbeead90a01b989b749723223e1579'),
(41, 42, 7, '111838', '949801', '2025-08-30 10:16:45', '2025-08-30 10:21:45', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '235250fc651af88110297a0bc22d1b92a35e65aca228792002237edaa993ed13'),
(42, 42, 7, '111838', '528744', '2025-08-30 10:27:03', '2025-08-30 10:32:03', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'af9b74a6fed0cc2d0e34fd7460e90ebd1172aed36e160d6f786c08ff9b881563'),
(43, 42, 7, '111838', '969474', '2025-08-30 11:21:04', '2025-08-30 11:26:04', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '54714c43d7859a3ae7cf82e38dfa6bec8895676a55823d97499e1ecb3f4a5716'),
(44, 42, 7, '111838', '741143', '2025-08-30 11:22:22', '2025-08-30 11:27:22', 'Yes', 2, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '7b0cee73f023b80c435b6e4d843884ba2623545a749270c1892f3a8b0d4e4934'),
(45, 42, 7, '111838', '865957', '2025-08-30 11:23:28', '2025-08-30 11:28:28', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '3525268dc1308b1dadc3528c84092b4264fd3ac98427891adea2924fcbddeb45'),
(46, 42, 7, '111838', '193336', '2025-08-30 11:24:22', '2025-08-30 11:29:22', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '595840098e3811088c25d337094011f32c7e73cddb087ac6fdc7aa4484d1fda5'),
(47, 42, 7, '111838', '135313', '2025-08-30 11:25:25', '2025-08-30 11:30:25', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'aa3489fe1ceedacadea7f987582da3a00317e6d2ec8932c50e56536af57840d7'),
(48, 42, 7, '111838', '280993', '2025-08-30 11:26:09', '2025-08-30 11:31:09', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '6fa9e983bab472975d9b2b48f2950651664b4c70cb82699c5d88b2a6e1176f86'),
(49, 42, 7, '111838', '031753', '2025-08-30 11:34:40', '2025-08-30 11:39:40', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '61a58a5c654a71c94678cd8b6967ca46ac0bfb1529b01058e17dc456fe5feaa0'),
(50, 42, 7, '111838', '446516', '2025-08-30 11:34:55', '2025-08-30 11:39:55', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2f91a9b0f649f51a75713ae7702e703a18f23c09227e963220a5ade5c2423536'),
(51, 42, 7, '111838', '865522', '2025-08-30 11:39:01', '2025-08-30 11:44:01', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'f517783e9413baa1c98479f9c879d25ec77d1d31ab63451ff9634fa3460df438'),
(52, 42, 7, '111838', '405678', '2025-08-30 11:39:49', '2025-08-30 11:44:49', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2356e899e4a1afb8857c3955a440e71b3a04f62d0af4f01f77671726024f6fc0'),
(53, 42, 7, '111838', '922924', '2025-08-30 11:42:07', '2025-08-30 11:47:07', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '5185043be5a86361874588cebbdcc166bc08461791bef06a06907a21af005786'),
(54, 42, 7, '111838', '046933', '2025-08-30 11:43:17', '2025-08-30 11:48:17', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:142.0) Gecko/20100101 Firefox/142.0', 'f1be75d20996678f09ea70944ced1db79886a3c2f881fc906da6808a241b564f'),
(55, 42, 7, '111838', '026691', '2025-08-30 11:44:19', '2025-08-30 11:49:19', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:142.0) Gecko/20100101 Firefox/142.0', '9f8642a19c8264e7e520e3ffdf9c677a6d225046b138b979a5314605e22a9c2b'),
(56, 42, 7, '111838', '562589', '2025-08-30 11:46:56', '2025-08-30 11:51:56', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '60e7839caba542b06bfec4bb1ea0f80a2320e6d649803b2f6282976c54258d86'),
(57, 42, 7, '111838', '178618', '2025-08-30 11:47:13', '2025-08-30 11:52:13', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '41b677d92f303e9dc919b09fe9fc9487e4d3427c08ff18e328a64ebcbcdb8181'),
(58, 42, 7, '111838', '538630', '2025-08-30 11:48:02', '2025-08-30 11:53:02', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '15f997dcb164c024afbf84032da4c0c03f5c3cdd5d368a2d5b9ea0f4b163b459'),
(59, 42, 7, '111838', '894646', '2025-08-30 11:56:41', '2025-08-30 12:01:41', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '3eeb19f78bb278f220139aef468ce91529c6f4db08a1224eb37817ba0cfb7fb2'),
(60, 42, 7, '111838', '756770', '2025-08-30 11:57:08', '2025-08-30 12:02:08', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '5319617ea07defc6d0c320c3654653b0d25258b4cdb6ca527d4520416dd3ee34'),
(61, 42, 7, '111838', '465944', '2025-08-30 11:58:50', '2025-08-30 12:03:50', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', 'c867d7152ae98cf1ff0840b69ecb7a7b8f537ede41f3417a78abe25f911f5dd9'),
(62, 42, 7, '111838', '699569', '2025-08-30 12:00:45', '2025-08-30 12:05:45', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '5a0abdb1509ed9b72b1dceaf8dc7a26eaa48c40b3935dcaa1126d0070a519f16'),
(63, 42, 7, '111838', '645124', '2025-08-30 12:01:16', '2025-08-30 12:06:16', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '6dbd7e531acbf99af03f5a462ec6a8dce79eb6e88ab4f5a10d504231133dbb0f'),
(64, 42, 7, '111838', '365426', '2025-08-30 12:03:21', '2025-08-30 12:08:21', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '1fab1522b8748c33f88c0de1770a1399c1b0fa99bf954bb51f6dbb0b4ba41d57'),
(65, 42, 7, '111838', '292792', '2025-08-30 12:03:46', '2025-08-30 12:08:46', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Mobile Safari/537.36', '90b370a091466b49929ec8644dfc53c718781d130dab06b6312ac499dcdd47bd'),
(66, 42, 7, '111838', '434144', '2025-08-30 12:08:27', '2025-08-30 12:13:27', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'ee2982e771824c8f00c166fd8f868f9d1288dae87652d1e43bc615a97c3b7f21'),
(67, 42, 7, '111838', '559583', '2025-08-30 12:09:19', '2025-08-30 12:14:19', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '1de63a1c9145fb70d0de6515257e2394a8e951c2bce286ac3fda3e5e05c78372'),
(68, 42, 7, '111838', '756115', '2025-08-30 12:09:43', '2025-08-30 12:14:43', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'b344915792dbea9a1c8ce2bd4a32e4132bb4449e8eebc791a898818157d77126'),
(69, 41, 7, '23188', '389080', '2025-08-30 12:16:57', '2025-08-30 12:21:57', 'Yes', 1, '127.0.0.1', 'Test Agent', 'dfde837a0aa6866a53b53997e3c45a2ab19cc7f755cfc110e3c62a7d5ee3f071'),
(70, 41, 7, '23188', '276249', '2025-08-30 12:17:21', '2025-08-30 12:22:21', 'Yes', 0, '127.0.0.1', 'Test Agent', 'acb73da3a438455bcb77195794750ebafed78a309e2d00ddd13e018c2a5243f2'),
(71, 41, 7, '23188', '222519', '2025-08-30 12:17:39', '2025-08-30 12:22:39', 'Yes', 0, '127.0.0.1', 'Test Agent', 'ab2bb4435f976e8df1139a194f29af596cebc90962e8793974cb388b92c44df1'),
(72, 42, 7, '111838', '564893', '2025-08-30 12:21:01', '2025-08-30 12:26:01', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'fda20613686126cebc4227ecb0c20f3463c0d16253ce94b458d6d69023000cbe'),
(73, 42, 7, '111838', '790632', '2025-08-30 12:22:22', '2025-08-30 12:27:22', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'bebd82535ef95913a0b48f582a7e0d6dabd04aae3e1d35d4f082ca5873fefc40'),
(74, 42, 7, '111838', '045804', '2025-08-30 12:26:46', '2025-08-30 12:31:46', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'f06ee9f413f28e8b3467f23a650b7f98ce4c8166d6794901de8e2d812b52c3a8'),
(75, 42, 7, '111838', '831804', '2025-08-30 12:33:40', '2025-08-30 12:38:40', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'aad2391d0cc61a352e9258641281a869b16186253c7eb02e25562e92d8334c15'),
(76, 42, 7, '111838', '721346', '2025-08-30 12:34:20', '2025-08-30 12:39:20', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '9638cf7b59a64d4767cda52e2e4c06d7585d33bd10c54533475351d1a96b9229'),
(77, 42, 7, '111838', '966714', '2025-08-30 12:35:27', '2025-08-30 12:40:27', 'Yes', 2, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '65c268c881398321b3c16567ac295cd65e499020183dbb15be64ea063a46d726'),
(78, 41, 7, '23188', '061087', '2025-08-30 12:35:29', '2025-08-30 12:40:29', 'No', 0, '127.0.0.1', 'Test Agent for Logging', '035996e56bff581d79b930f11d6d5ea4d69dfa417064d42ddbfebe74c7717970'),
(79, 42, 7, '111838', '748932', '2025-08-30 12:45:42', '2025-08-30 12:50:42', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'a78f3a59ea6083ff70e12c4c97d8d4a3af5ccd8f20f5f5efdb716e76e5753ac9'),
(80, 2050, 7, '15600027', '819893', '2025-08-30 12:46:13', '2025-08-30 12:51:13', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'fb0b3fa3f79afb20d0a9a95f8badc0ddd70b3a9f845175db20e8b0f64cc974b5'),
(81, 2050, 7, '15600027', '476480', '2025-08-30 13:06:53', '2025-08-30 13:11:53', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'a248f976cfa342a5e54dea768281250a2cca855ff0a219ab9fcd70040a44da71'),
(82, 2050, 7, '15600027', '894455', '2025-08-30 13:21:36', '2025-08-30 13:26:36', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '9a5ec9474eee21b18ea67d52305f105f9329bb6cf312aff120e7642e5ef87cc3'),
(83, 2050, 7, '15600027', '784115', '2025-08-30 13:32:17', '2025-08-30 13:37:17', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'aa9fa237efd904c920e722c29c91becf607f48ca195da45d6eef3d7fe69e50d3'),
(84, 2050, 7, '15600027', '575942', '2025-08-30 13:51:45', '2025-08-30 13:56:45', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', 'e618610c028278dde0e8b75c295fd7464804eda47e3dba9b82e31670e2e2b39a'),
(85, 2050, 7, '15600027', '479832', '2025-09-14 18:56:19', '2025-09-14 19:01:19', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '076a8f7ee53eef0e09c4a5caca68fb0fc2050f66305b1be6ec326e159679666f'),
(86, 2050, 7, '15600027', '431538', '2025-09-14 18:58:30', '2025-09-14 19:03:30', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'b7064ef36dc336b2a4e02523620886f3b632dcd414f788ddaf10b13bbbbbcf5d'),
(87, 74, 7, '1', '291439', '2025-09-14 19:13:32', '2025-09-14 19:18:32', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '6887f3c4d17034edd2616bb0bdffdc2242ad42c0a5c4c79948b24c90794588c2'),
(88, 74, 7, '1', '311898', '2025-09-14 19:13:36', '2025-09-14 19:18:36', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '8000b7b061953bfd325724cd2d1ca016bebd43ca3b47cd574c5b121fb5cda8d4'),
(89, 2050, 7, '15600027', '008524', '2025-09-14 19:14:20', '2025-09-14 19:19:20', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '9fa14521673a48a235acac0264c650c3bee9caa2ab6b49d90c1d29320ad0b227'),
(90, 74, 7, '1', '002966', '2025-09-14 19:15:12', '2025-09-14 19:20:12', 'Yes', 0, '127.0.0.1', 'Debug Script', '26438383eebcb0b789201e35c59199b72d928f49199362ab46a38d08c6092cd4'),
(91, 74, 7, '1', '536607', '2025-09-14 19:15:58', '2025-09-14 19:20:58', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '301d415baad9df46707680a25ca0da022f9517fad3e2cf6dce25c503d9f7b164'),
(92, 74, 7, '1', '875814', '2025-09-14 19:16:25', '2025-09-14 19:21:25', 'Yes', 0, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '067f1928320e519fe709184765c75b7d91d157bc6924e707836abe50a767aee7'),
(93, 74, 7, '1', '638079', '2025-09-16 05:26:58', '2025-09-16 05:31:58', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '48c0e7c7cff200948c3d1cbab5bfc66b462dda7b6c2df387264d5fa3037f67cb'),
(94, 74, 7, '1', '819060', '2025-09-16 05:38:40', '2025-09-16 05:43:40', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '6a9f5d6d65796169923bcec9300d5c3e21093e8ec17f630163c79f37daab60ef'),
(95, 74, 7, '1', '086501', '2025-09-16 05:48:41', '2025-09-16 05:53:41', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '252f6b57eb97e592e2bf3d61b27800d3bc754e79e03941483720f2a2e39cd6a0'),
(96, 74, 7, '1', '745532', '2025-09-16 06:07:32', '2025-09-16 06:12:32', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '1966609fce98c63108686c8f87b96fc4c16dc18126abc27f57341c87906b3b4d'),
(97, 74, 7, '1', '633839', '2025-09-16 06:53:02', '2025-09-16 06:58:02', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'd4384f72514c8e2162a1d5b34eb5f4326c71e643a5d1c3680f377c8deb7fac76'),
(98, 74, 7, '1', '785950', '2025-09-16 11:50:08', '2025-09-16 11:55:08', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '0c13d03274a2fd46274a59c33403720d7e9ea9635b32ca8cffbc0314bab34f5d'),
(99, 88, 7, '2', '609849', '2025-09-16 11:51:34', '2025-09-16 11:56:34', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'c58f1c1adaca2785c63a444c254de988faed8c5f423714996965c036c1a4ffe2'),
(100, 88, 7, '2', '236481', '2025-09-16 18:13:16', '2025-09-16 18:18:16', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'f09c3c52e7e46e9887d84cb20fb70f756dfaa5185900e35125ed41a688f465ac'),
(101, 88, 7, '2', '166418', '2025-09-16 18:20:52', '2025-09-16 18:25:52', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '878ab24adf7111a3d499255a31c9d4ea8e978836c5c785ab7380eb58ad4670e5'),
(102, 88, 7, '2', '063859', '2025-09-16 18:31:48', '2025-09-16 18:36:48', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '41905712cfdc30b8f46d840d2e8ba3e90870545fbaea7f02a5cec0a80c9cbe6f'),
(103, 74, 7, '1', '774106', '2025-09-16 18:33:51', '2025-09-16 18:38:51', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'c5d92855465a2d29cf2c69c316361e4f9b273c1e7c906de40226a9020db17d1b'),
(104, 42, 7, '111838', '414998', '2025-09-16 18:36:20', '2025-09-16 18:41:20', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '643e178b4540badaa7b4daaa3f7d711710b0bc834051f37918d941358c752103'),
(105, 46, 7, '93269', '547216', '2025-09-16 18:38:42', '2025-09-16 18:43:42', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '41a21f2eddc781cfa95384fbb6521aa5d258abd6261aac6f7dcb10bd6268bed0'),
(106, 46, 7, '93269', '396623', '2025-09-16 18:45:55', '2025-09-16 18:50:55', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2ce273ef57beef165234ac0f7c24844d1067e066e8e7388c4b6001e2e15d85e1'),
(107, 74, 7, '1', '397907', '2025-09-16 18:46:59', '2025-09-16 18:51:59', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '5293efee256f219c4b7c419fa9498a500fde06f7033842131e8fafa53a6c94cd'),
(108, 2050, 7, '15600027', '286644', '2025-09-16 18:49:04', '2025-09-16 18:54:04', 'Yes', 1, '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', 'b92eb25ae231d7fc8bee36f607603797e4de2b1290f09aaabdffc884fc4690c3');

-- --------------------------------------------------------

--
-- Table structure for table `user_workflow_log`
--

CREATE TABLE `user_workflow_log` (
  `log_id` int NOT NULL,
  `user_id` int NOT NULL COMMENT 'User ID being managed',
  `action_type` enum('Created','Modified','Approved','Rejected','Resubmitted') COLLATE utf8mb4_unicode_ci NOT NULL,
  `performed_by` int NOT NULL,
  `action_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `old_data` json DEFAULT NULL,
  `new_data` json DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log for user workflow actions';

--
-- Dumping data for table `user_workflow_log`
--

INSERT INTO `user_workflow_log` (`log_id`, `user_id`, `action_type`, `performed_by`, `action_date`, `old_data`, `new_data`, `remarks`, `ip_address`, `user_agent`) VALUES
(1, 74, 'Created', 41, '2022-02-25 17:17:20', NULL, NULL, 'Historical record - migrated during user workflow implementation', NULL, NULL),
(2, 88, 'Created', 41, '2022-07-01 15:09:42', NULL, NULL, 'Historical record - migrated during user workflow implementation', NULL, NULL),
(3, 3057, 'Created', 41, '2025-04-10 14:32:56', NULL, NULL, 'Historical record - migrated during user workflow implementation', NULL, NULL),
(4, 3058, 'Created', 41, '2025-04-24 17:57:28', NULL, NULL, 'Historical record - migrated during user workflow implementation', NULL, NULL),
(5, 3059, 'Created', 41, '2025-04-24 20:03:05', NULL, NULL, 'Historical record - migrated during user workflow implementation', NULL, NULL),
(6, 3060, 'Created', 41, '2025-05-26 19:28:13', NULL, NULL, 'Historical record - migrated during user workflow implementation', NULL, NULL),
(7, 3063, 'Created', 41, '2025-06-15 19:45:41', NULL, NULL, 'Historical record - migrated during user workflow implementation', NULL, NULL),
(8, 3072, 'Created', 41, '2025-08-31 00:11:52', NULL, NULL, 'Historical record - migrated during user workflow implementation', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `validation_reports`
--

CREATE TABLE `validation_reports` (
  `val_wf_id` varchar(45) NOT NULL,
  `sop1_doc_number` varchar(200) DEFAULT NULL,
  `sop1_entered_by_user_id` int DEFAULT NULL,
  `sop1_entered_date` datetime DEFAULT NULL,
  `sop2_doc_number` varchar(200) DEFAULT NULL,
  `sop2_entered_by_user_id` int DEFAULT NULL,
  `sop2_entered_date` datetime DEFAULT NULL,
  `sop3_doc_number` varchar(200) DEFAULT NULL,
  `sop3_entered_by_user_id` int DEFAULT NULL,
  `sop3_entered_date` datetime DEFAULT NULL,
  `sop4_doc_number` varchar(200) DEFAULT NULL,
  `sop4_entered_by_user_id` int DEFAULT NULL,
  `sop4_entered_date` datetime DEFAULT NULL,
  `sop5_doc_number` varchar(200) DEFAULT NULL,
  `sop5_entered_by_user_id` int DEFAULT NULL,
  `sop5_entered_date` datetime DEFAULT NULL,
  `sop6_doc_number` varchar(200) DEFAULT NULL,
  `sop6_entered_by_user_id` int DEFAULT NULL,
  `sop6_entered_date` datetime DEFAULT NULL,
  `sop7_doc_number` varchar(200) DEFAULT NULL,
  `sop7_entered_by_user_id` int DEFAULT NULL,
  `sop7_entered_date` datetime DEFAULT NULL,
  `sop8_doc_number` varchar(200) DEFAULT NULL,
  `sop8_entered_by_user_id` int DEFAULT NULL,
  `sop8_entered_date` datetime DEFAULT NULL,
  `sop9_doc_number` varchar(200) DEFAULT NULL,
  `sop9_entered_by_user_id` int DEFAULT NULL,
  `sop9_entered_date` datetime DEFAULT NULL,
  `sop10_doc_number` varchar(200) DEFAULT NULL,
  `sop10_entered_by_user_id` int DEFAULT NULL,
  `sop10_entered_date` datetime DEFAULT NULL,
  `sop11_doc_number` varchar(200) DEFAULT NULL,
  `sop11_entered_by_user_id` int DEFAULT NULL,
  `sop11_entered_date` datetime DEFAULT NULL,
  `sop12_doc_number` varchar(200) DEFAULT NULL,
  `sop12_entered_by_user_id` int DEFAULT NULL,
  `sop12_entered_date` datetime DEFAULT NULL,
  `cal_instru1_code_number` varchar(100) DEFAULT NULL,
  `cal_instru1_cal_done_on` date DEFAULT NULL,
  `cal_instru1_cal_due_on` date DEFAULT NULL,
  `cal_instru1_entered_by_user_id` int DEFAULT NULL,
  `cal_instru1_entered_date` datetime DEFAULT NULL,
  `cal_instru2_code_number` varchar(100) DEFAULT NULL,
  `cal_instru2_cal_done_on` date DEFAULT NULL,
  `cal_instru2_cal_due_on` date DEFAULT NULL,
  `cal_instru2_entered_by_user_id` int DEFAULT NULL,
  `cal_instru2_entered_date` datetime DEFAULT NULL,
  `cal_instru3_code_number` varchar(100) DEFAULT NULL,
  `cal_instru3_cal_done_on` date DEFAULT NULL,
  `cal_instru3_cal_due_on` date DEFAULT NULL,
  `cal_instru3_entered_by_user_id` int DEFAULT NULL,
  `cal_instru3_entered_date` datetime DEFAULT NULL,
  `cal_instru4_code_number` varchar(100) DEFAULT NULL,
  `cal_instru4_cal_done_on` date DEFAULT NULL,
  `cal_instru4_cal_due_on` date DEFAULT NULL,
  `cal_instru4_entered_by_user_id` int DEFAULT NULL,
  `cal_instru4_entered_date` datetime DEFAULT NULL,
  `cal_instru5_code_number` varchar(100) DEFAULT NULL,
  `cal_instru5_cal_done_on` date DEFAULT NULL,
  `cal_instru5_cal_due_on` date DEFAULT NULL,
  `cal_instru5_entered_by_user_id` int DEFAULT NULL,
  `cal_instru5_entered_date` datetime DEFAULT NULL,
  `test1_observation` varchar(45) DEFAULT NULL,
  `test2_observation` varchar(45) DEFAULT NULL,
  `test3_observation` varchar(45) DEFAULT NULL,
  `test4_observation` varchar(45) DEFAULT NULL,
  `test5_observation` varchar(45) DEFAULT NULL,
  `test6_observation` varchar(45) DEFAULT NULL,
  `test7_observation` varchar(45) DEFAULT NULL,
  `test8_observation` varchar(45) DEFAULT NULL,
  `test9_observation` varchar(45) DEFAULT NULL,
  `test10_observation` varchar(45) DEFAULT NULL,
  `test11_observation` varchar(45) DEFAULT NULL,
  `test12_observation` varchar(45) DEFAULT NULL,
  `test13_observation` varchar(45) DEFAULT NULL,
  `test14_observation` varchar(45) DEFAULT NULL,
  `test15_observation` varchar(45) DEFAULT NULL,
  `deviation` varchar(1000) DEFAULT NULL,
  `summary` varchar(1000) DEFAULT NULL,
  `recommendationn` varchar(1000) DEFAULT NULL,
  `deviation_review` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Mandatory deviation review field for team approval submission stage - max 500 chars',
  `justification` varchar(500) DEFAULT NULL,
  `deviation_remark_val_begin` varchar(500) DEFAULT NULL,
  `sop13_doc_number` varchar(200) DEFAULT NULL,
  `sop13_entered_by_user_id` int DEFAULT NULL,
  `sop13_entered_date` datetime DEFAULT NULL,
  `test16_observation` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `validation_reports`
--

INSERT INTO `validation_reports` (`val_wf_id`, `sop1_doc_number`, `sop1_entered_by_user_id`, `sop1_entered_date`, `sop2_doc_number`, `sop2_entered_by_user_id`, `sop2_entered_date`, `sop3_doc_number`, `sop3_entered_by_user_id`, `sop3_entered_date`, `sop4_doc_number`, `sop4_entered_by_user_id`, `sop4_entered_date`, `sop5_doc_number`, `sop5_entered_by_user_id`, `sop5_entered_date`, `sop6_doc_number`, `sop6_entered_by_user_id`, `sop6_entered_date`, `sop7_doc_number`, `sop7_entered_by_user_id`, `sop7_entered_date`, `sop8_doc_number`, `sop8_entered_by_user_id`, `sop8_entered_date`, `sop9_doc_number`, `sop9_entered_by_user_id`, `sop9_entered_date`, `sop10_doc_number`, `sop10_entered_by_user_id`, `sop10_entered_date`, `sop11_doc_number`, `sop11_entered_by_user_id`, `sop11_entered_date`, `sop12_doc_number`, `sop12_entered_by_user_id`, `sop12_entered_date`, `cal_instru1_code_number`, `cal_instru1_cal_done_on`, `cal_instru1_cal_due_on`, `cal_instru1_entered_by_user_id`, `cal_instru1_entered_date`, `cal_instru2_code_number`, `cal_instru2_cal_done_on`, `cal_instru2_cal_due_on`, `cal_instru2_entered_by_user_id`, `cal_instru2_entered_date`, `cal_instru3_code_number`, `cal_instru3_cal_done_on`, `cal_instru3_cal_due_on`, `cal_instru3_entered_by_user_id`, `cal_instru3_entered_date`, `cal_instru4_code_number`, `cal_instru4_cal_done_on`, `cal_instru4_cal_due_on`, `cal_instru4_entered_by_user_id`, `cal_instru4_entered_date`, `cal_instru5_code_number`, `cal_instru5_cal_done_on`, `cal_instru5_cal_due_on`, `cal_instru5_entered_by_user_id`, `cal_instru5_entered_date`, `test1_observation`, `test2_observation`, `test3_observation`, `test4_observation`, `test5_observation`, `test6_observation`, `test7_observation`, `test8_observation`, `test9_observation`, `test10_observation`, `test11_observation`, `test12_observation`, `test13_observation`, `test14_observation`, `test15_observation`, `deviation`, `summary`, `recommendationn`, `deviation_review`, `justification`, `deviation_remark_val_begin`, `sop13_doc_number`, `sop13_entered_by_user_id`, `sop13_entered_date`, `test16_observation`) VALUES
('V-1-7-1760163302-6M', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', 'Rucha Sample Text', 42, '2025-10-11 12:02:27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pass', 'Pass', 'Pass', '', '', 'Pass', '', '', 'Pass', '', '', '', '', '', '', 'Rucha Sample Text', 'RD sample verification', 'RD Sample Recomm', 'RD NA', 'Rucha Sample Text', NULL, 'Rucha Sample Text', 42, '2025-10-11 12:02:27', '');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `vendor_id` int NOT NULL,
  `vendor_code` varchar(45) DEFAULT NULL,
  `vendor_name` varchar(100) DEFAULT NULL,
  `vendor_spoc_name` varchar(100) DEFAULT NULL,
  `vendor_spoc_mobile` varchar(45) DEFAULT NULL,
  `vendor_spoc_email` varchar(100) DEFAULT NULL,
  `vendor_status` varchar(45) DEFAULT 'Active',
  `vendor_creation_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `vendor_last_modification_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `vendor_password` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`vendor_id`, `vendor_code`, `vendor_name`, `vendor_spoc_name`, `vendor_spoc_mobile`, `vendor_spoc_email`, `vendor_status`, `vendor_creation_datetime`, `vendor_last_modification_datetime`, `vendor_password`) VALUES
(1, 'TV1', 'Test Vendor One I', 'POC ONE', '9876543211', 'test@palcoa.com', 'Active', '2020-09-26 01:32:12', '2020-09-26 01:32:12', NULL),
(2, 'TV2', 'Test Vendor Two', 'POC TWO', '9876543210', 'test@palcoa.com', 'Active', NULL, NULL, NULL),
(3, 'TV3', 'Test Vendor Three', 'POC THREE', '9876543210', 'test@palcoa.com', 'Active', '2022-02-25 16:06:42', '2022-02-25 16:06:42', NULL),
(4, NULL, 'Test Vendor Five', 'Omkar', '9819316186', 'test@vi.com', 'Active', '2025-05-26 16:16:53', '2025-05-26 16:16:53', NULL),
(5, NULL, 'Te', 'Te', '9819316186', 't@t.com', 'Inactive', '2025-06-15 20:05:03', '2025-06-15 20:05:03', NULL),
(6, NULL, 'QTS', 'T', '8425806006', 'om@test.com', 'Active', '2025-08-31 00:32:20', '2025-08-31 00:32:20', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_email_reminder_delivery_stats`
-- (See below for the actual view)
--
CREATE TABLE `vw_email_reminder_delivery_stats` (
`emails_failed` bigint
,`emails_sent` bigint
,`job_name` varchar(100)
,`sent_date` date
,`total_emails` bigint
,`total_failed` decimal(32,0)
,`total_successful` decimal(32,0)
,`unit_id` int
,`unit_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_email_reminder_job_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_email_reminder_job_summary` (
`email_logs_count` bigint
,`emails_failed` int
,`emails_sent` int
,`execution_start_time` datetime
,`execution_time_seconds` int
,`final_message` text
,`job_name` varchar(100)
,`status` enum('running','completed','failed','skipped')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_email_reminder_recipient_tracking`
-- (See below for the actual view)
--
CREATE TABLE `vw_email_reminder_recipient_tracking` (
`bounce_reason` text
,`delivery_datetime` datetime
,`delivery_status` enum('pending','sent','failed','bounced','delivered','opened','clicked')
,`email_subject` text
,`job_name` varchar(100)
,`recipient_email` varchar(255)
,`recipient_type` enum('to','cc','bcc')
,`smtp_response` text
,`unit_id` int
,`unit_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_equipment_schedule_changes`
-- (See below for the actual view)
--
CREATE TABLE `v_equipment_schedule_changes` (
`affected_test_origin_display` varchar(19)
,`avg_days_optimized` decimal(14,4)
,`creations` decimal(23,0)
,`equipment_code` varchar(45)
,`equipment_id` int
,`first_change` datetime
,`frequency` varchar(5)
,`latest_change` datetime
,`system_auto_created_changes` decimal(23,0)
,`system_original_changes` decimal(23,0)
,`total_changes` bigint
,`unit_name` varchar(100)
,`updates` decimal(23,0)
,`user_manual_adhoc_changes` decimal(23,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_frequency_compliance_analysis`
-- (See below for the actual view)
--
CREATE TABLE `v_frequency_compliance_analysis` (
`avg_schedule_shift_days` decimal(14,4)
,`compliance_percentage` decimal(29,2)
,`compliant_changes` decimal(23,0)
,`equipment_id` int
,`frequency` varchar(5)
,`schedule_year` int
,`total_changes` bigint
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_routine_test_context`
-- (See below for the actual view)
--
CREATE TABLE `v_routine_test_context` (
`auto_created` char(1)
,`equip_id` int
,`equipment_code` varchar(45)
,`frequency_code` varchar(45)
,`parent_routine_test_wf_id` varchar(45)
,`routine_current_year` year
,`routine_test_sch_id` int
,`routine_test_wf_id` varchar(45)
,`routine_test_wf_planned_start_date` date
,`test_id` int
,`unit_id` int
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_schedule_change_history`
-- (See below for the actual view)
--
CREATE TABLE `v_schedule_change_history` (
`change_id` int
,`change_reason` varchar(200)
,`change_summary` varchar(31)
,`change_timestamp` datetime
,`change_type` enum('schedule_update','schedule_creation','manual_adjustment','system_correction')
,`days_shifted` int
,`equipment_code` varchar(45)
,`execution_variance_days` int
,`frequency` varchar(5)
,`new_planned_date` date
,`original_planned_date` date
,`triggering_execution_date` date
,`unit_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_test_specific_data_with_users`
-- (See below for the actual view)
--
CREATE TABLE `v_test_specific_data_with_users` (
`data_json` json
,`entered_by_id` int
,`entered_by_name` varchar(200)
,`entered_date` timestamp
,`id` int
,`modified_by_id` int
,`modified_by_name` varchar(200)
,`modified_date` timestamp
,`section_type` varchar(50)
,`test_val_wf_id` varchar(50)
,`unit_id` int
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_validation_context`
-- (See below for the actual view)
--
CREATE TABLE `v_validation_context` (
`auto_created` char(1)
,`equip_id` int
,`equipment_code` varchar(45)
,`frequency_code` varchar(5)
,`parent_val_wf_id` varchar(45)
,`primary_test_id` int
,`secondary_test_id` int
,`unit_id` int
,`val_sch_id` int
,`val_wf_id` varchar(45)
,`val_wf_planned_start_date` date
,`validation_current_year` year
);

-- --------------------------------------------------------

--
-- Table structure for table `workflow_stages`
--

CREATE TABLE `workflow_stages` (
  `wf_stage_id` int NOT NULL,
  `wf_stage` varchar(45) DEFAULT NULL,
  `wf_stage_description` varchar(200) DEFAULT NULL,
  `wf_type` varchar(45) DEFAULT NULL,
  `status` varchar(45) DEFAULT 'Active',
  `created_date_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_modified_date_time` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `workflow_stages`
--

INSERT INTO `workflow_stages` (`wf_stage_id`, `wf_stage`, `wf_stage_description`, `wf_type`, `status`, `created_date_time`, `last_modified_date_time`) VALUES
(1, '1', 'Task assigned.', 'External Test', 'Active', '2020-10-01 02:23:47', '2020-10-01 02:23:47'),
(2, '2', 'Task submitted for enginnering team\'s approval', 'External Test', 'Active', '2020-10-01 02:23:47', '2020-10-01 02:23:47'),
(3, '3A', 'Task approved by engineering and assigned to QA.', 'External Test', 'Active', '2020-10-01 02:23:47', '2020-10-01 02:23:47'),
(4, '3B', 'Task rejected by engineering and assigned back to the vendor.', 'External Test', 'Active', '2020-10-01 02:23:47', '2020-10-01 02:23:47'),
(5, '4A', 'Task approved by QA and assigned to engineering.', 'External Test', 'Active', '2020-10-01 02:23:47', '2020-10-01 02:23:47'),
(6, '4B', 'Task rejected by QA and assigned back to the vendor.', 'External Test', 'Active', '2020-10-01 02:23:47', '2020-10-01 02:23:47'),
(7, '5', 'Task complete.', 'External Test', 'Active', '2020-10-01 02:23:47', '2020-10-01 02:23:47'),
(8, '1', 'Validation workflow initiated.', 'Validation', 'Active', '2020-10-15 00:41:54', '2020-10-15 00:41:54'),
(9, '2', 'Pending for level I approval.', 'Validation', 'Active', '2020-11-02 07:52:21', '2020-11-02 07:52:21'),
(10, '3', 'Pending for Level II approval.', 'Validation', 'Active', '2020-11-02 07:52:21', '2020-11-02 07:52:21'),
(11, '4', 'Pending for Level III approval.', 'Validation', 'Active', '2020-11-02 07:52:21', '2020-11-02 07:52:21'),
(12, '5', 'Validation Workflow approved.', 'Validation', 'Active', '2020-11-02 07:52:21', '2020-11-02 07:52:21'),
(13, '0', 'Validation workflow not yet initiated.', 'Validation', 'Active', '2020-11-28 23:58:03', '2020-11-28 23:58:03'),
(14, '1', 'Routine Test workflow initiated.', 'RT', 'Active', '2021-07-23 02:28:52', '2021-07-23 02:28:52'),
(15, '5', 'Routine Test Workflow approved.', 'RT', 'Active', '2021-07-23 02:28:52', '2021-07-23 02:28:52'),
(16, '0', 'Routine Test workflow not yet initiated.', 'RT', 'Active', '2021-07-23 02:28:52', '2021-07-23 02:28:52'),
(17, '99', 'Routine Test workflow marked completed.', 'RT', 'Active', '2021-09-01 12:58:50', '2021-09-01 12:58:50'),
(18, '99', 'Validation Workflow marked completed.', 'Validation', 'Active', '2021-09-01 12:58:50', '2021-09-01 12:58:50'),
(19, '1PRV', 'Offline Task awaiting checker review', 'External Test', 'Active', '2025-09-12 00:39:08', '2025-09-12 00:39:08'),
(20, '1RRV', 'Offline Task review rejected by vendor checker', 'External Test', 'Active', '2025-09-12 01:27:24', '2025-09-12 01:27:24'),
(21, '3BPRV', 'Task rejected by engineering and assigned back to the vendor checker.', 'External Test', 'Active', '2025-09-12 17:52:16', '2025-09-12 17:52:16'),
(22, '4BPRV', 'Task rejected by engineering and assigned back to the vendor checker.', 'External Test', 'Active', '2025-09-17 10:33:17', '2025-09-17 10:33:17'),
(23, '98A', 'Validation Study Termination Requested', 'Validation', 'Active', '2025-09-30 16:47:23', '2025-09-30 16:47:23'),
(24, '98B', 'Validation Study Termination Reviewed by Engg Dept Head', 'Validation', 'Active', '2025-09-30 16:47:23', '2025-09-30 16:47:23'),
(25, '98', 'Validation Study Termination Approved by QA Head', 'Validation', 'Active', '2025-09-30 16:47:23', '2025-09-30 16:47:23'),
(26, '98D', 'Validation Study Termination Rejected by Engg Dept ', 'Validation', 'Active', '2025-09-30 16:47:23', '2025-09-30 16:47:23'),
(27, '98E', 'Validation Study Termination Rejected by QA Head', 'Validation', 'Active', '2025-09-30 16:47:23', '2025-09-30 16:47:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approver_remarks`
--
ALTER TABLE `approver_remarks`
  ADD PRIMARY KEY (`remarks_id`),
  ADD KEY `test_wf_id` (`test_wf_id`),
  ADD KEY `val_wf_id` (`val_wf_id`),
  ADD KEY `fk_approver_user_id` (`user_id`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`audit_trail_id`),
  ADD KEY `val_wf_id` (`val_wf_id`),
  ADD KEY `test_wf_id` (`test_wf_id`),
  ADD KEY `idx_audit_trail_timestamp` (`time_stamp`),
  ADD KEY `idx_audit_trail_user_timestamp` (`user_id`,`time_stamp`),
  ADD KEY `idx_audit_trail_wf_stage` (`wf_stage`),
  ADD KEY `idx_audit_trail_val_wf` (`val_wf_id`),
  ADD KEY `idx_audit_trail_test_wf` (`test_wf_id`);

--
-- Indexes for table `auto_schedule_config`
--
ALTER TABLE `auto_schedule_config`
  ADD PRIMARY KEY (`config_id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD KEY `idx_config_key` (`config_key`);

--
-- Indexes for table `auto_schedule_deployment_backup`
--
ALTER TABLE `auto_schedule_deployment_backup`
  ADD PRIMARY KEY (`backup_id`);

--
-- Indexes for table `auto_schedule_log`
--
ALTER TABLE `auto_schedule_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_trigger_type` (`trigger_type`),
  ADD KEY `idx_trigger_timestamp` (`trigger_timestamp`),
  ADD KEY `idx_equipment_id` (`equipment_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `demotbl`
--
ALTER TABLE `demotbl`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `department_name` (`department_name`);

--
-- Indexes for table `equipments`
--
ALTER TABLE `equipments`
  ADD PRIMARY KEY (`equipment_id`),
  ADD UNIQUE KEY `equipment_code_UNIQUE` (`equipment_code`),
  ADD KEY `fk_equip_unit` (`unit_id`),
  ADD KEY `equipment_code` (`equipment_code`),
  ADD KEY `idx_equip_id` (`equipment_id`),
  ADD KEY `idx_equipments_status_dept` (`equipment_status`,`department_id`),
  ADD KEY `idx_equipments_dept_status` (`department_id`,`equipment_status`),
  ADD KEY `idx_equipments_status` (`equipment_status`),
  ADD KEY `idx_equipments_type_status` (`equipment_type`,`equipment_status`),
  ADD KEY `idx_equipments_creation_date` (`equipment_creation_datetime`),
  ADD KEY `idx_equipments_validation_date` (`first_validation_date`),
  ADD KEY `idx_equipments_frequency` (`validation_frequency`,`equipment_status`);

--
-- Indexes for table `equipment_frequency_tracking`
--
ALTER TABLE `equipment_frequency_tracking`
  ADD PRIMARY KEY (`tracking_id`),
  ADD UNIQUE KEY `unique_equipment` (`equipment_id`),
  ADD KEY `idx_equipment_id` (`equipment_id`),
  ADD KEY `idx_last_validation_date` (`last_validation_date`);

--
-- Indexes for table `equipment_test_vendor_mapping`
--
ALTER TABLE `equipment_test_vendor_mapping`
  ADD PRIMARY KEY (`mapping_id`),
  ADD UNIQUE KEY `equipment_id_UNIQUE` (`equipment_id`,`test_id`,`frequency_label`),
  ADD KEY `idx_frequency_label` (`frequency_label`);

--
-- Indexes for table `erf_mappings`
--
ALTER TABLE `erf_mappings`
  ADD PRIMARY KEY (`erf_mapping_id`),
  ADD UNIQUE KEY `uk_equipment_room_filter` (`equipment_id`,`room_loc_id`,`filter_name`),
  ADD KEY `idx_equipment_id` (`equipment_id`),
  ADD KEY `idx_room_loc_id` (`room_loc_id`),
  ADD KEY `idx_status` (`erf_mapping_status`),
  ADD KEY `idx_creation_date` (`creation_datetime`),
  ADD KEY `idx_filter_group_id` (`filter_group_id`),
  ADD KEY `idx_erf_mappings_filter_id` (`filter_id`);

--
-- Indexes for table `error_log`
--
ALTER TABLE `error_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `filters`
--
ALTER TABLE `filters`
  ADD PRIMARY KEY (`filter_id`),
  ADD UNIQUE KEY `unique_filter_code` (`filter_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_installation_date` (`installation_date`),
  ADD KEY `idx_planned_due_date` (`planned_due_date`),
  ADD KEY `idx_unit_id` (`unit_id`),
  ADD KEY `idx_filter_group_id` (`filter_type_id`);

--
-- Indexes for table `filter_groups`
--
ALTER TABLE `filter_groups`
  ADD PRIMARY KEY (`filter_group_id`),
  ADD UNIQUE KEY `uk_filter_group_name` (`filter_group_name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_creation_date` (`creation_datetime`);

--
-- Indexes for table `frequency_intervals`
--
ALTER TABLE `frequency_intervals`
  ADD PRIMARY KEY (`frequency_code`),
  ADD KEY `idx_frequency` (`frequency_code`);

--
-- Indexes for table `instruments`
--
ALTER TABLE `instruments`
  ADD PRIMARY KEY (`instrument_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_instrument_type` (`instrument_type`),
  ADD KEY `idx_calibration_due` (`calibration_due_on`),
  ADD KEY `idx_status` (`instrument_status`),
  ADD KEY `pending_approval_id` (`pending_approval_id`),
  ADD KEY `idx_instruments_approval_status` (`approval_status`),
  ADD KEY `idx_instruments_vendor_approval` (`vendor_id`,`approval_status`),
  ADD KEY `idx_instruments_vendor` (`vendor_id`),
  ADD KEY `idx_instruments_status` (`instrument_status`),
  ADD KEY `idx_instruments_creation` (`created_date`),
  ADD KEY `idx_instruments_calibration_due` (`calibration_due_on`),
  ADD KEY `idx_instruments_approval` (`approval_status`),
  ADD KEY `fk_instruments_submitted_by` (`submitted_by`),
  ADD KEY `idx_instruments_workflow` (`instrument_status`,`submitted_by`,`vendor_id`),
  ADD KEY `idx_instruments_checker` (`checker_id`,`checker_date`);

--
-- Indexes for table `instrument_approval_audit`
--
ALTER TABLE `instrument_approval_audit`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_approval_audit` (`approval_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_performed_by` (`performed_by`);

--
-- Indexes for table `instrument_calibration_approvals`
--
ALTER TABLE `instrument_calibration_approvals`
  ADD PRIMARY KEY (`approval_id`),
  ADD KEY `idx_instrument_approval` (`instrument_id`,`approval_status`),
  ADD KEY `idx_vendor_pending` (`vendor_id`,`approval_status`),
  ADD KEY `idx_maker` (`created_by_vendor_user`),
  ADD KEY `idx_checker` (`reviewed_by_vendor_user`),
  ADD KEY `idx_workflow_action` (`workflow_action`);

--
-- Indexes for table `instrument_certificate_history`
--
ALTER TABLE `instrument_certificate_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `idx_instrument_id` (`instrument_id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_uploaded_date` (`uploaded_date`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_instrument_active` (`instrument_id`,`is_active`,`uploaded_date` DESC);

--
-- Indexes for table `instrument_workflow_log`
--
ALTER TABLE `instrument_workflow_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_instrument_workflow_log_instrument` (`instrument_id`),
  ADD KEY `idx_instrument_workflow_log_date` (`action_date`),
  ADD KEY `idx_instrument_workflow_log_user` (`performed_by`);

--
-- Indexes for table `log`
--
ALTER TABLE `log`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `raw_data_templates`
--
ALTER TABLE `raw_data_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_test_active` (`test_id`,`is_active`),
  ADD KEY `idx_effective_date` (`effective_date`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_download_count` (`download_count`),
  ADD KEY `idx_active_recent` (`is_active`,`effective_date` DESC),
  ADD KEY `idx_effective_till_date` (`effective_till_date`);

--
-- Indexes for table `room_locations`
--
ALTER TABLE `room_locations`
  ADD PRIMARY KEY (`room_loc_id`),
  ADD KEY `idx_room_name` (`room_loc_name`),
  ADD KEY `idx_creation_date` (`creation_datetime`),
  ADD KEY `idx_modification_date` (`last_modification_datetime`);

--
-- Indexes for table `routine_tests_schedules`
--
ALTER TABLE `routine_tests_schedules`
  ADD PRIMARY KEY (`routine_test_schedule_id`);

--
-- Indexes for table `scheduled_emails`
--
ALTER TABLE `scheduled_emails`
  ADD PRIMARY KEY (`communication_id`);

--
-- Indexes for table `tbl_database_migrations`
--
ALTER TABLE `tbl_database_migrations`
  ADD PRIMARY KEY (`migration_id`),
  ADD UNIQUE KEY `uk_migration_name` (`migration_name`);

--
-- Indexes for table `tbl_email_configuration`
--
ALTER TABLE `tbl_email_configuration`
  ADD PRIMARY KEY (`email_configuration_id`),
  ADD KEY `idx_unit_event` (`unit_id`,`event_name`),
  ADD KEY `idx_email_enabled` (`email_enabled`),
  ADD KEY `idx_last_sent_date` (`last_sent_date`);

--
-- Indexes for table `tbl_email_events`
--
ALTER TABLE `tbl_email_events`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `tbl_email_reminder_job_logs`
--
ALTER TABLE `tbl_email_reminder_job_logs`
  ADD PRIMARY KEY (`job_execution_id`),
  ADD KEY `idx_job_name` (`job_name`),
  ADD KEY `idx_execution_start_time` (`execution_start_time`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `tbl_email_reminder_logs`
--
ALTER TABLE `tbl_email_reminder_logs`
  ADD PRIMARY KEY (`email_log_id`),
  ADD KEY `idx_job_execution_id` (`job_execution_id`),
  ADD KEY `idx_job_name` (`job_name`),
  ADD KEY `idx_unit_id` (`unit_id`),
  ADD KEY `idx_sent_datetime` (`sent_datetime`),
  ADD KEY `idx_delivery_status` (`delivery_status`);

--
-- Indexes for table `tbl_email_reminder_recipients`
--
ALTER TABLE `tbl_email_reminder_recipients`
  ADD PRIMARY KEY (`recipient_log_id`),
  ADD KEY `idx_email_log_id` (`email_log_id`),
  ADD KEY `idx_recipient_email` (`recipient_email`),
  ADD KEY `idx_recipient_type` (`recipient_type`),
  ADD KEY `idx_delivery_status` (`delivery_status`),
  ADD KEY `idx_delivery_datetime` (`delivery_datetime`);

--
-- Indexes for table `tbl_email_reminder_system_logs`
--
ALTER TABLE `tbl_email_reminder_system_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_log_level` (`log_level`),
  ADD KEY `idx_log_source` (`log_source`),
  ADD KEY `idx_log_datetime` (`log_datetime`);

--
-- Indexes for table `tbl_prod_config`
--
ALTER TABLE `tbl_prod_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_proposed_routine_test_schedules`
--
ALTER TABLE `tbl_proposed_routine_test_schedules`
  ADD PRIMARY KEY (`proposed_sch_row_id`);

--
-- Indexes for table `tbl_proposed_val_schedules`
--
ALTER TABLE `tbl_proposed_val_schedules`
  ADD PRIMARY KEY (`proposed_sch_row_id`),
  ADD KEY `idx_tbl_proposed_val_schedules_frequency` (`frequency_type`),
  ADD KEY `idx_tbl_proposed_val_schedules_cycle` (`cycle_position`,`cycle_count`),
  ADD KEY `idx_tbl_proposed_val_schedules_schedule_equip` (`schedule_id`,`equip_id`);

--
-- Indexes for table `tbl_report_approvers`
--
ALTER TABLE `tbl_report_approvers`
  ADD PRIMARY KEY (`val_wf_id`,`iteration_id`);

--
-- Indexes for table `tbl_routine_tests_requests`
--
ALTER TABLE `tbl_routine_tests_requests`
  ADD PRIMARY KEY (`routine_test_request_id`),
  ADD UNIQUE KEY `routine_test_status_UNIQUE` (`unit_id`,`equipment_id`,`test_id`,`routine_test_status`),
  ADD KEY `idx_adhoc_frequency` (`adhoc_frequency`),
  ADD KEY `idx_unit_adhoc_frequency` (`unit_id`,`adhoc_frequency`);

--
-- Indexes for table `tbl_routine_test_schedules`
--
ALTER TABLE `tbl_routine_test_schedules`
  ADD PRIMARY KEY (`routine_test_sch_id`),
  ADD KEY `idx_auto_created_rt` (`auto_created`),
  ADD KEY `idx_parent_rt` (`parent_routine_test_wf_id`),
  ADD KEY `idx_routine_schedules_equipment_date` (`equip_id`,`routine_test_wf_planned_start_date`),
  ADD KEY `idx_test_origin_status` (`test_origin`,`routine_test_wf_status`);

--
-- Indexes for table `tbl_routine_test_schedule_changes`
--
ALTER TABLE `tbl_routine_test_schedule_changes`
  ADD PRIMARY KEY (`change_id`),
  ADD KEY `idx_equipment_date` (`equipment_id`,`change_timestamp`),
  ADD KEY `idx_affected_test` (`affected_routine_test_wf_id`),
  ADD KEY `idx_triggering_test` (`triggering_routine_test_wf_id`),
  ADD KEY `idx_schedule_year` (`schedule_year`,`change_type`),
  ADD KEY `idx_change_type` (`change_type`,`frequency`),
  ADD KEY `idx_affected_test_origin` (`affected_test_origin`,`change_type`),
  ADD KEY `idx_triggering_test_origin` (`triggering_test_origin`);

--
-- Indexes for table `tbl_routine_test_wf_schedule_requests`
--
ALTER TABLE `tbl_routine_test_wf_schedule_requests`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `schedule_year_unit_id_UNIQUE` (`schedule_year`,`unit_id`);

--
-- Indexes for table `tbl_routine_test_wf_tracking_details`
--
ALTER TABLE `tbl_routine_test_wf_tracking_details`
  ADD PRIMARY KEY (`routine_test_wf_tracking_id`);

--
-- Indexes for table `tbl_test_finalisation_details`
--
ALTER TABLE `tbl_test_finalisation_details`
  ADD PRIMARY KEY (`test_id`),
  ADD KEY `idx_test_wf_id` (`test_wf_id`),
  ADD KEY `idx_test_finalised_by` (`test_finalised_by`),
  ADD KEY `idx_witness` (`witness`),
  ADD KEY `idx_creation_datetime` (`creation_datetime`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `tbl_test_schedules_tracking`
--
ALTER TABLE `tbl_test_schedules_tracking`
  ADD PRIMARY KEY (`test_sch_id`),
  ADD KEY `idx_test_wf_stage_vendor_unit` (`test_wf_current_stage`,`vendor_id`,`unit_id`),
  ADD KEY `idx_test_wf_stage_unit` (`test_wf_current_stage`,`unit_id`),
  ADD KEY `idx_val_wf_id_stage` (`val_wf_id`,`test_wf_current_stage`,`test_wf_id`),
  ADD KEY `idx_auto_schedule_stage` (`test_wf_current_stage`,`auto_schedule_processed`),
  ADD KEY `idx_test_tracking_stage_date` (`test_wf_current_stage`,`test_conducted_date`),
  ADD KEY `idx_data_entry_mode` (`data_entry_mode`);

--
-- Indexes for table `tbl_training_details`
--
ALTER TABLE `tbl_training_details`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbl_uploads`
--
ALTER TABLE `tbl_uploads`
  ADD PRIMARY KEY (`upload_id`);

--
-- Indexes for table `tbl_val_schedules`
--
ALTER TABLE `tbl_val_schedules`
  ADD PRIMARY KEY (`val_sch_id`),
  ADD KEY `idx_auto_created` (`auto_created`),
  ADD KEY `idx_parent_val` (`parent_val_wf_id`),
  ADD KEY `idx_frequency` (`frequency_code`),
  ADD KEY `idx_val_schedules_equipment_date` (`equip_id`,`val_wf_planned_start_date`);

--
-- Indexes for table `tbl_val_wf_approval_tracking_details`
--
ALTER TABLE `tbl_val_wf_approval_tracking_details`
  ADD PRIMARY KEY (`val_wf_approval_trcking_id`),
  ADD UNIQUE KEY `val_wf_id_UNIQUE` (`val_wf_id`,`iteration_id`),
  ADD KEY `idx_val_wf_id_approval` (`val_wf_id`);

--
-- Indexes for table `tbl_val_wf_schedule_requests`
--
ALTER TABLE `tbl_val_wf_schedule_requests`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `schedule_year_unit_id_UNIQUE` (`schedule_year`,`unit_id`);

--
-- Indexes for table `tbl_val_wf_tracking_details`
--
ALTER TABLE `tbl_val_wf_tracking_details`
  ADD PRIMARY KEY (`val_wf_tracking_id`),
  ADD KEY `idx_val_tracking_stage_unit` (`val_wf_current_stage`,`unit_id`),
  ADD KEY `idx_stage_before_termination` (`stage_before_termination`);

--
-- Indexes for table `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`test_id`),
  ADD KEY `idx_dependent_tests` (`dependent_tests`(100)),
  ADD KEY `idx_paper_on_glass` (`paper_on_glass_enabled`);

--
-- Indexes for table `test_instruments`
--
ALTER TABLE `test_instruments`
  ADD PRIMARY KEY (`mapping_id`),
  ADD KEY `idx_test_val_wf_id` (`test_val_wf_id`),
  ADD KEY `idx_instrument_id` (`instrument_id`),
  ADD KEY `idx_added_by` (`added_by`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_unit_id` (`unit_id`);

--
-- Indexes for table `test_specific_data`
--
ALTER TABLE `test_specific_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_test_val_wf_id` (`test_val_wf_id`),
  ADD KEY `idx_section_type` (`section_type`),
  ADD KEY `idx_entered_by` (`entered_by`),
  ADD KEY `idx_modified_by` (`modified_by`),
  ADD KEY `idx_unit_id` (`unit_id`),
  ADD KEY `idx_test_specific_data_filter_id` (`filter_id`);

--
-- Indexes for table `trigger_error_log`
--
ALTER TABLE `trigger_error_log`
  ADD PRIMARY KEY (`error_id`),
  ADD KEY `idx_trigger_name` (`trigger_name`),
  ADD KEY `idx_error_timestamp` (`error_timestamp`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`unit_id`),
  ADD KEY `idx_units_primary_secondary` (`unit_id`,`primary_test_id`,`secondary_test_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `employee_id_UNIQUE` (`employee_id`,`user_type`),
  ADD UNIQUE KEY `user_type_UNIQUE` (`employee_id`,`user_type`),
  ADD KEY `fk_user_1` (`department_id`),
  ADD KEY `fk_user_2` (`unit_id`),
  ADD KEY `idx_users_employee_id` (`employee_id`),
  ADD KEY `idx_users_status_dept` (`user_status`,`department_id`),
  ADD KEY `idx_users_status` (`user_status`),
  ADD KEY `idx_users_email` (`user_email`),
  ADD KEY `idx_users_created_date` (`user_created_datetime`),
  ADD KEY `idx_users_admin` (`is_admin`,`user_status`),
  ADD KEY `idx_users_dept_head` (`is_dept_head`,`department_id`),
  ADD KEY `fk_users_submitted_by` (`submitted_by`),
  ADD KEY `idx_users_workflow` (`user_status`,`submitted_by`,`unit_id`,`user_type`),
  ADD KEY `idx_users_checker` (`checker_id`,`checker_date`);

--
-- Indexes for table `user_otp_sessions`
--
ALTER TABLE `user_otp_sessions`
  ADD PRIMARY KEY (`otp_session_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `fk_otp_sessions_unit_id` (`unit_id`),
  ADD KEY `idx_cleanup` (`expires_at`,`is_used`);

--
-- Indexes for table `user_workflow_log`
--
ALTER TABLE `user_workflow_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_workflow_log_user` (`user_id`),
  ADD KEY `idx_user_workflow_log_date` (`action_date`),
  ADD KEY `idx_user_workflow_log_performer` (`performed_by`);

--
-- Indexes for table `validation_reports`
--
ALTER TABLE `validation_reports`
  ADD PRIMARY KEY (`val_wf_id`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`vendor_id`);

--
-- Indexes for table `workflow_stages`
--
ALTER TABLE `workflow_stages`
  ADD PRIMARY KEY (`wf_stage_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approver_remarks`
--
ALTER TABLE `approver_remarks`
  MODIFY `remarks_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24719;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `audit_trail_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29071;

--
-- AUTO_INCREMENT for table `auto_schedule_config`
--
ALTER TABLE `auto_schedule_config`
  MODIFY `config_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `auto_schedule_deployment_backup`
--
ALTER TABLE `auto_schedule_deployment_backup`
  MODIFY `backup_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `auto_schedule_log`
--
ALTER TABLE `auto_schedule_log`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `demotbl`
--
ALTER TABLE `demotbl`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `equipment_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72144;

--
-- AUTO_INCREMENT for table `equipment_frequency_tracking`
--
ALTER TABLE `equipment_frequency_tracking`
  MODIFY `tracking_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `equipment_test_vendor_mapping`
--
ALTER TABLE `equipment_test_vendor_mapping`
  MODIFY `mapping_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9244;

--
-- AUTO_INCREMENT for table `erf_mappings`
--
ALTER TABLE `erf_mappings`
  MODIFY `erf_mapping_id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier for ERF mapping', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `error_log`
--
ALTER TABLE `error_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `filters`
--
ALTER TABLE `filters`
  MODIFY `filter_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `filter_groups`
--
ALTER TABLE `filter_groups`
  MODIFY `filter_group_id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier for filter group', AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `instrument_approval_audit`
--
ALTER TABLE `instrument_approval_audit`
  MODIFY `audit_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `instrument_calibration_approvals`
--
ALTER TABLE `instrument_calibration_approvals`
  MODIFY `approval_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `instrument_certificate_history`
--
ALTER TABLE `instrument_certificate_history`
  MODIFY `history_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `instrument_workflow_log`
--
ALTER TABLE `instrument_workflow_log`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `log`
--
ALTER TABLE `log`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68075;

--
-- AUTO_INCREMENT for table `raw_data_templates`
--
ALTER TABLE `raw_data_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `room_locations`
--
ALTER TABLE `room_locations`
  MODIFY `room_loc_id` int NOT NULL AUTO_INCREMENT COMMENT 'Unique identifier for room location', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `scheduled_emails`
--
ALTER TABLE `scheduled_emails`
  MODIFY `communication_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_database_migrations`
--
ALTER TABLE `tbl_database_migrations`
  MODIFY `migration_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_email_configuration`
--
ALTER TABLE `tbl_email_configuration`
  MODIFY `email_configuration_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `tbl_email_events`
--
ALTER TABLE `tbl_email_events`
  MODIFY `event_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_email_reminder_job_logs`
--
ALTER TABLE `tbl_email_reminder_job_logs`
  MODIFY `job_execution_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_email_reminder_logs`
--
ALTER TABLE `tbl_email_reminder_logs`
  MODIFY `email_log_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_email_reminder_recipients`
--
ALTER TABLE `tbl_email_reminder_recipients`
  MODIFY `recipient_log_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_email_reminder_system_logs`
--
ALTER TABLE `tbl_email_reminder_system_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_proposed_routine_test_schedules`
--
ALTER TABLE `tbl_proposed_routine_test_schedules`
  MODIFY `proposed_sch_row_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2142;

--
-- AUTO_INCREMENT for table `tbl_proposed_val_schedules`
--
ALTER TABLE `tbl_proposed_val_schedules`
  MODIFY `proposed_sch_row_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2555;

--
-- AUTO_INCREMENT for table `tbl_routine_tests_requests`
--
ALTER TABLE `tbl_routine_tests_requests`
  MODIFY `routine_test_request_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10003;

--
-- AUTO_INCREMENT for table `tbl_routine_test_schedules`
--
ALTER TABLE `tbl_routine_test_schedules`
  MODIFY `routine_test_sch_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10340;

--
-- AUTO_INCREMENT for table `tbl_routine_test_schedule_changes`
--
ALTER TABLE `tbl_routine_test_schedule_changes`
  MODIFY `change_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tbl_routine_test_wf_schedule_requests`
--
ALTER TABLE `tbl_routine_test_wf_schedule_requests`
  MODIFY `schedule_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=152;

--
-- AUTO_INCREMENT for table `tbl_routine_test_wf_tracking_details`
--
ALTER TABLE `tbl_routine_test_wf_tracking_details`
  MODIFY `routine_test_wf_tracking_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=893;

--
-- AUTO_INCREMENT for table `tbl_test_finalisation_details`
--
ALTER TABLE `tbl_test_finalisation_details`
  MODIFY `test_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `tbl_test_schedules_tracking`
--
ALTER TABLE `tbl_test_schedules_tracking`
  MODIFY `test_sch_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11637;

--
-- AUTO_INCREMENT for table `tbl_training_details`
--
ALTER TABLE `tbl_training_details`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=915;

--
-- AUTO_INCREMENT for table `tbl_uploads`
--
ALTER TABLE `tbl_uploads`
  MODIFY `upload_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9463;

--
-- AUTO_INCREMENT for table `tbl_val_schedules`
--
ALTER TABLE `tbl_val_schedules`
  MODIFY `val_sch_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10057;

--
-- AUTO_INCREMENT for table `tbl_val_wf_approval_tracking_details`
--
ALTER TABLE `tbl_val_wf_approval_tracking_details`
  MODIFY `val_wf_approval_trcking_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=797;

--
-- AUTO_INCREMENT for table `tbl_val_wf_schedule_requests`
--
ALTER TABLE `tbl_val_wf_schedule_requests`
  MODIFY `schedule_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT for table `tbl_val_wf_tracking_details`
--
ALTER TABLE `tbl_val_wf_tracking_details`
  MODIFY `val_wf_tracking_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=923;

--
-- AUTO_INCREMENT for table `tests`
--
ALTER TABLE `tests`
  MODIFY `test_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `test_instruments`
--
ALTER TABLE `test_instruments`
  MODIFY `mapping_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `test_specific_data`
--
ALTER TABLE `test_specific_data`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `trigger_error_log`
--
ALTER TABLE `trigger_error_log`
  MODIFY `error_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3073;

--
-- AUTO_INCREMENT for table `user_otp_sessions`
--
ALTER TABLE `user_otp_sessions`
  MODIFY `otp_session_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT for table `user_workflow_log`
--
ALTER TABLE `user_workflow_log`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `vendor_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `workflow_stages`
--
ALTER TABLE `workflow_stages`
  MODIFY `wf_stage_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

-- --------------------------------------------------------

--
-- Structure for view `vw_email_reminder_delivery_stats`
--
DROP TABLE IF EXISTS `vw_email_reminder_delivery_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_email_reminder_delivery_stats`  AS SELECT `e`.`job_name` AS `job_name`, `e`.`unit_id` AS `unit_id`, `u`.`unit_name` AS `unit_name`, cast(`e`.`sent_datetime` as date) AS `sent_date`, count(0) AS `total_emails`, sum(`e`.`successful_sends`) AS `total_successful`, sum(`e`.`failed_sends`) AS `total_failed`, count((case when (`e`.`delivery_status` = 'sent') then 1 end)) AS `emails_sent`, count((case when (`e`.`delivery_status` = 'failed') then 1 end)) AS `emails_failed` FROM (`tbl_email_reminder_logs` `e` left join `units` `u` on((`e`.`unit_id` = `u`.`unit_id`))) GROUP BY `e`.`job_name`, `e`.`unit_id`, cast(`e`.`sent_datetime` as date) ORDER BY `e`.`sent_datetime` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_email_reminder_job_summary`
--
DROP TABLE IF EXISTS `vw_email_reminder_job_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_email_reminder_job_summary`  AS SELECT `j`.`job_name` AS `job_name`, `j`.`execution_start_time` AS `execution_start_time`, `j`.`status` AS `status`, `j`.`emails_sent` AS `emails_sent`, `j`.`emails_failed` AS `emails_failed`, `j`.`execution_time_seconds` AS `execution_time_seconds`, count(`e`.`email_log_id`) AS `email_logs_count`, `j`.`final_message` AS `final_message` FROM (`tbl_email_reminder_job_logs` `j` left join `tbl_email_reminder_logs` `e` on((`j`.`job_execution_id` = `e`.`job_execution_id`))) GROUP BY `j`.`job_execution_id` ORDER BY `j`.`execution_start_time` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vw_email_reminder_recipient_tracking`
--
DROP TABLE IF EXISTS `vw_email_reminder_recipient_tracking`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_email_reminder_recipient_tracking`  AS SELECT `r`.`recipient_email` AS `recipient_email`, `r`.`recipient_type` AS `recipient_type`, `r`.`delivery_status` AS `delivery_status`, `r`.`delivery_datetime` AS `delivery_datetime`, `e`.`job_name` AS `job_name`, `e`.`unit_id` AS `unit_id`, `u`.`unit_name` AS `unit_name`, `e`.`email_subject` AS `email_subject`, `r`.`smtp_response` AS `smtp_response`, `r`.`bounce_reason` AS `bounce_reason` FROM ((`tbl_email_reminder_recipients` `r` join `tbl_email_reminder_logs` `e` on((`r`.`email_log_id` = `e`.`email_log_id`))) left join `units` `u` on((`e`.`unit_id` = `u`.`unit_id`))) ORDER BY `r`.`delivery_datetime` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_equipment_schedule_changes`
--
DROP TABLE IF EXISTS `v_equipment_schedule_changes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_equipment_schedule_changes`  AS SELECT `e`.`equipment_code` AS `equipment_code`, `u`.`unit_name` AS `unit_name`, `s`.`equipment_id` AS `equipment_id`, `s`.`frequency` AS `frequency`, (case when (`s`.`affected_test_origin` is null) then 'System Original' when (`s`.`affected_test_origin` = 'system_auto_created') then 'System Auto-Created' when (`s`.`affected_test_origin` = 'user_manual_adhoc') then 'User Manual Ad-hoc' else `s`.`affected_test_origin` end) AS `affected_test_origin_display`, count(0) AS `total_changes`, sum((case when (`s`.`change_type` = 'schedule_update') then 1 else 0 end)) AS `updates`, sum((case when (`s`.`change_type` = 'schedule_creation') then 1 else 0 end)) AS `creations`, sum((case when (`s`.`affected_test_origin` is null) then 1 else 0 end)) AS `system_original_changes`, sum((case when (`s`.`affected_test_origin` = 'system_auto_created') then 1 else 0 end)) AS `system_auto_created_changes`, sum((case when (`s`.`affected_test_origin` = 'user_manual_adhoc') then 1 else 0 end)) AS `user_manual_adhoc_changes`, avg((case when (`s`.`days_shifted` is not null) then abs(`s`.`days_shifted`) else NULL end)) AS `avg_days_optimized`, min(`s`.`change_timestamp`) AS `first_change`, max(`s`.`change_timestamp`) AS `latest_change` FROM ((`tbl_routine_test_schedule_changes` `s` left join `equipments` `e` on((`s`.`equipment_id` = `e`.`equipment_id`))) left join `units` `u` on((`s`.`unit_id` = `u`.`unit_id`))) GROUP BY `s`.`equipment_id`, `s`.`frequency`, `s`.`affected_test_origin` ORDER BY `total_changes` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_frequency_compliance_analysis`
--
DROP TABLE IF EXISTS `v_frequency_compliance_analysis`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_frequency_compliance_analysis`  AS SELECT `tbl_routine_test_schedule_changes`.`equipment_id` AS `equipment_id`, `tbl_routine_test_schedule_changes`.`frequency` AS `frequency`, `tbl_routine_test_schedule_changes`.`schedule_year` AS `schedule_year`, count(0) AS `total_changes`, sum((case when (`tbl_routine_test_schedule_changes`.`frequency_compliance_maintained` = true) then 1 else 0 end)) AS `compliant_changes`, round(((100.0 * sum((case when (`tbl_routine_test_schedule_changes`.`frequency_compliance_maintained` = true) then 1 else 0 end))) / count(0)),2) AS `compliance_percentage`, avg((case when ((`tbl_routine_test_schedule_changes`.`change_type` = 'schedule_update') and (`tbl_routine_test_schedule_changes`.`days_shifted` is not null)) then abs(`tbl_routine_test_schedule_changes`.`days_shifted`) else NULL end)) AS `avg_schedule_shift_days` FROM `tbl_routine_test_schedule_changes` GROUP BY `tbl_routine_test_schedule_changes`.`equipment_id`, `tbl_routine_test_schedule_changes`.`frequency`, `tbl_routine_test_schedule_changes`.`schedule_year` ORDER BY `tbl_routine_test_schedule_changes`.`equipment_id` ASC, `tbl_routine_test_schedule_changes`.`schedule_year` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `v_routine_test_context`
--
DROP TABLE IF EXISTS `v_routine_test_context`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_routine_test_context`  AS SELECT `rts`.`routine_test_sch_id` AS `routine_test_sch_id`, `rts`.`routine_test_wf_id` AS `routine_test_wf_id`, `rts`.`equip_id` AS `equip_id`, `rts`.`unit_id` AS `unit_id`, `rts`.`test_id` AS `test_id`, `rts`.`routine_test_wf_planned_start_date` AS `routine_test_wf_planned_start_date`, year(`rts`.`routine_test_wf_planned_start_date`) AS `routine_current_year`, `rtr`.`test_frequency` AS `frequency_code`, `e`.`equipment_code` AS `equipment_code`, `rts`.`parent_routine_test_wf_id` AS `parent_routine_test_wf_id`, `rts`.`auto_created` AS `auto_created` FROM ((`tbl_routine_test_schedules` `rts` join `tbl_routine_tests_requests` `rtr` on((`rts`.`routine_test_req_id` = `rtr`.`routine_test_request_id`))) join `equipments` `e` on((`rts`.`equip_id` = `e`.`equipment_id`))) WHERE (`rts`.`routine_test_wf_status` = 'Active') ;

-- --------------------------------------------------------

--
-- Structure for view `v_schedule_change_history`
--
DROP TABLE IF EXISTS `v_schedule_change_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_schedule_change_history`  AS SELECT `s`.`change_id` AS `change_id`, `e`.`equipment_code` AS `equipment_code`, `u`.`unit_name` AS `unit_name`, `s`.`change_type` AS `change_type`, `s`.`original_planned_date` AS `original_planned_date`, `s`.`new_planned_date` AS `new_planned_date`, `s`.`days_shifted` AS `days_shifted`, `s`.`triggering_execution_date` AS `triggering_execution_date`, `s`.`execution_variance_days` AS `execution_variance_days`, `s`.`frequency` AS `frequency`, `s`.`change_reason` AS `change_reason`, `s`.`change_timestamp` AS `change_timestamp`, (case when (`s`.`change_type` = 'schedule_creation') then 'NEW SCHEDULE CREATED' when (`s`.`days_shifted` > 0) then concat('DELAYED BY ',`s`.`days_shifted`,' DAYS') when (`s`.`days_shifted` < 0) then concat('ACCELERATED BY ',abs(`s`.`days_shifted`),' DAYS') else 'NO DATE CHANGE' end) AS `change_summary` FROM ((`tbl_routine_test_schedule_changes` `s` left join `equipments` `e` on((`s`.`equipment_id` = `e`.`equipment_id`))) left join `units` `u` on((`s`.`unit_id` = `u`.`unit_id`))) ORDER BY `s`.`change_timestamp` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_test_specific_data_with_users`
--
DROP TABLE IF EXISTS `v_test_specific_data_with_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_test_specific_data_with_users`  AS SELECT `tsd`.`id` AS `id`, `tsd`.`test_val_wf_id` AS `test_val_wf_id`, `tsd`.`section_type` AS `section_type`, `tsd`.`data_json` AS `data_json`, `tsd`.`entered_date` AS `entered_date`, `tsd`.`modified_date` AS `modified_date`, `tsd`.`unit_id` AS `unit_id`, `u1`.`user_name` AS `entered_by_name`, `u1`.`user_id` AS `entered_by_id`, `u2`.`user_name` AS `modified_by_name`, `u2`.`user_id` AS `modified_by_id` FROM ((`test_specific_data` `tsd` left join `users` `u1` on((`tsd`.`entered_by` = `u1`.`user_id`))) left join `users` `u2` on((`tsd`.`modified_by` = `u2`.`user_id`))) ;

-- --------------------------------------------------------

--
-- Structure for view `v_validation_context`
--
DROP TABLE IF EXISTS `v_validation_context`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_validation_context`  AS SELECT `vs`.`val_sch_id` AS `val_sch_id`, `vs`.`val_wf_id` AS `val_wf_id`, `vs`.`equip_id` AS `equip_id`, `vs`.`unit_id` AS `unit_id`, `vs`.`val_wf_planned_start_date` AS `val_wf_planned_start_date`, year(`vs`.`val_wf_planned_start_date`) AS `validation_current_year`, `vs`.`frequency_code` AS `frequency_code`, `e`.`equipment_code` AS `equipment_code`, `u`.`primary_test_id` AS `primary_test_id`, `u`.`secondary_test_id` AS `secondary_test_id`, `vs`.`parent_val_wf_id` AS `parent_val_wf_id`, `vs`.`auto_created` AS `auto_created` FROM ((`tbl_val_schedules` `vs` join `equipments` `e` on((`vs`.`equip_id` = `e`.`equipment_id`))) join `units` `u` on((`vs`.`unit_id` = `u`.`unit_id`))) WHERE (`vs`.`val_wf_status` = 'Active') ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approver_remarks`
--
ALTER TABLE `approver_remarks`
  ADD CONSTRAINT `fk_approver_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `equipments`
--
ALTER TABLE `equipments`
  ADD CONSTRAINT `fk_equip_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`);

--
-- Constraints for table `equipment_frequency_tracking`
--
ALTER TABLE `equipment_frequency_tracking`
  ADD CONSTRAINT `equipment_frequency_tracking_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`equipment_id`) ON DELETE CASCADE;

--
-- Constraints for table `erf_mappings`
--
ALTER TABLE `erf_mappings`
  ADD CONSTRAINT `erf_mappings_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`equipment_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `erf_mappings_ibfk_2` FOREIGN KEY (`room_loc_id`) REFERENCES `room_locations` (`room_loc_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_erf_mappings_filter_group` FOREIGN KEY (`filter_group_id`) REFERENCES `filter_groups` (`filter_group_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `filters`
--
ALTER TABLE `filters`
  ADD CONSTRAINT `fk_filters_filter_type` FOREIGN KEY (`filter_type_id`) REFERENCES `filter_groups` (`filter_group_id`);

--
-- Constraints for table `instruments`
--
ALTER TABLE `instruments`
  ADD CONSTRAINT `fk_instruments_checker_id` FOREIGN KEY (`checker_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_instruments_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `instruments_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`),
  ADD CONSTRAINT `instruments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `instruments_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `instruments_ibfk_4` FOREIGN KEY (`pending_approval_id`) REFERENCES `instrument_calibration_approvals` (`approval_id`);

--
-- Constraints for table `instrument_approval_audit`
--
ALTER TABLE `instrument_approval_audit`
  ADD CONSTRAINT `instrument_approval_audit_ibfk_1` FOREIGN KEY (`approval_id`) REFERENCES `instrument_calibration_approvals` (`approval_id`),
  ADD CONSTRAINT `instrument_approval_audit_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `instrument_calibration_approvals`
--
ALTER TABLE `instrument_calibration_approvals`
  ADD CONSTRAINT `instrument_calibration_approvals_ibfk_1` FOREIGN KEY (`created_by_vendor_user`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `instrument_calibration_approvals_ibfk_2` FOREIGN KEY (`reviewed_by_vendor_user`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `instrument_calibration_approvals_ibfk_3` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`vendor_id`);

--
-- Constraints for table `instrument_certificate_history`
--
ALTER TABLE `instrument_certificate_history`
  ADD CONSTRAINT `fk_certificate_history_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `instrument_workflow_log`
--
ALTER TABLE `instrument_workflow_log`
  ADD CONSTRAINT `instrument_workflow_log_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `raw_data_templates`
--
ALTER TABLE `raw_data_templates`
  ADD CONSTRAINT `fk_raw_templates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_raw_templates_test_id` FOREIGN KEY (`test_id`) REFERENCES `tests` (`test_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_email_reminder_logs`
--
ALTER TABLE `tbl_email_reminder_logs`
  ADD CONSTRAINT `tbl_email_reminder_logs_ibfk_1` FOREIGN KEY (`job_execution_id`) REFERENCES `tbl_email_reminder_job_logs` (`job_execution_id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_email_reminder_recipients`
--
ALTER TABLE `tbl_email_reminder_recipients`
  ADD CONSTRAINT `tbl_email_reminder_recipients_ibfk_1` FOREIGN KEY (`email_log_id`) REFERENCES `tbl_email_reminder_logs` (`email_log_id`) ON DELETE CASCADE;

--
-- Constraints for table `test_specific_data`
--
ALTER TABLE `test_specific_data`
  ADD CONSTRAINT `fk_test_specific_data_entered_by` FOREIGN KEY (`entered_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_test_specific_data_modified_by` FOREIGN KEY (`modified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_test_specific_data_unit` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`),
  ADD CONSTRAINT `fk_user_2` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`),
  ADD CONSTRAINT `fk_users_checker_id` FOREIGN KEY (`checker_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_otp_sessions`
--
ALTER TABLE `user_otp_sessions`
  ADD CONSTRAINT `fk_otp_sessions_unit_id` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_workflow_log`
--
ALTER TABLE `user_workflow_log`
  ADD CONSTRAINT `user_workflow_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_workflow_log_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

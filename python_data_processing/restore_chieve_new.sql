IF DB_ID(N'ChieveNew') IS NOT NULL
BEGIN
  ALTER DATABASE [ChieveNew] SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
  DROP DATABASE [ChieveNew];
END
RESTORE DATABASE [ChieveNew]
FROM DISK = N'F:\laragon\www\poscontinentalwholesale\production sql\Chieve new.bak'
WITH MOVE N'Chieve' TO N'C:\SQLData\ChieveNew.mdf',
     MOVE N'Chieve_log' TO N'C:\SQLData\ChieveNew_log.ldf',
     REPLACE, STATS = 25;

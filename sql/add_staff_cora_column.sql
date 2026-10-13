-- staff.cora: CORA role tier (one-off change to odb's staff table).
-- 0 = none, 1 = Super Admin, 2 = Corporate Admin, 3 = Outlet PIC, 4 = Outlet User.
ALTER TABLE staff
    ADD COLUMN cora INT NOT NULL DEFAULT 0
    COMMENT '0 = none, 1 = super admin, 2 = corporate admin, 3 = outlet pic, 4 = outlet user'
    AFTER edm;

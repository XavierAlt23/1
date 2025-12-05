import "dotenv/config";
import express from "express";
import supabase from "./supabaseClient.js";

const app = express();
app.use(express.json());

/* ===========================================================
   ROLES
=========================================================== */

// GET /roles
app.get("/roles", async (req, res) => {
    const { data, error } = await supabase
        .from("role")
        .select("*");

    if (error) return res.status(500).json({ error: error.message });
    res.json(data);
});

// GET /roles/:id
app.get("/roles/:id", async (req, res) => {
    const { data, error } = await supabase
        .from("role")
        .select("*")
        .eq("RoleID", req.params.id)
        .single();

    if (error) return res.status(404).json({ error: "Role not found" });
    res.json(data);
});

// POST /roles
app.post("/roles", async (req, res) => {
    const { RoleName, Description } = req.body;

    const { data, error } = await supabase
        .from("role")
        .insert([{ RoleName, Description }])
        .select();

    if (error) return res.status(500).json({ error: error.message });
    res.status(201).json(data[0]);
});

// PUT /roles/:id
app.put("/roles/:id", async (req, res) => {
    const { RoleName, Description } = req.body;

    const { data, error } = await supabase
        .from("role")
        .update({ RoleName, Description })
        .eq("RoleID", req.params.id)
        .select();

    if (error) return res.status(500).json({ error: error.message });
    res.json(data[0]);
});

// DELETE /roles/:id
app.delete("/roles/:id", async (req, res) => {
    const { error } = await supabase
        .from("role")
        .delete()
        .eq("RoleID", req.params.id);

    if (error) return res.status(500).json({ error: error.message });

    res.json({
        status: "ok",
        message: "Role deleted"
    });
});

/* ===========================================================
   PERMISSIONS
=========================================================== */

// GET /permissions
app.get("/permissions", async (req, res) => {
    const { data, error } = await supabase
        .from("permission")
        .select("*");

    if (error) return res.status(500).json({ error: error.message });
    res.json(data);
});

// GET /permissions/:id
app.get("/permissions/:id", async (req, res) => {
    const { data, error } = await supabase
        .from("permission")
        .select("*")
        .eq("PermissionID", req.params.id)
        .single();

    if (error) return res.status(404).json({ error: "Permission not found" });
    res.json(data);
});

// POST /permissions
app.post("/permissions", async (req, res) => {
    const { PermissionName, Module, Action } = req.body;

    const { data, error } = await supabase
        .from("permission")
        .insert([{ PermissionName, Module, Action }])
        .select();

    if (error) return res.status(500).json({ error: error.message });
    res.status(201).json(data[0]);
});

// DELETE /permissions/:id
app.delete("/permissions/:id", async (req, res) => {
    const { error } = await supabase
        .from("permission")
        .delete()
        .eq("PermissionID", req.params.id);

    if (error) return res.status(500).json({ error: error.message });

    res.json({
        status: "ok",
        message: "Permission deleted"
    });
});

/* ===========================================================
   ROLE PERMISSIONS
=========================================================== */

// GET /role_permissions
app.get("/role_permissions", async (req, res) => {
    const { data, error } = await supabase
        .from("role_permission")
        .select("*");

    if (error) return res.status(500).json({ error: error.message });
    res.json(data);
});

// GET /role_permissions/:id
app.get("/role_permissions/:id", async (req, res) => {
    const { data, error } = await supabase
        .from("role_permission")
        .select("*")
        .eq("RolePermissionID", req.params.id)
        .single();

    if (error) return res.status(404).json({ error: "RolePermission not found" });
    res.json(data);
});

// POST /role_permissions
app.post("/role_permissions", async (req, res) => {
    const { RoleID, PermissionID } = req.body;

    const { data, error } = await supabase
        .from("role_permission")
        .insert([{ RoleID, PermissionID }])
        .select();

    if (error) return res.status(400).json({ error: error.message });
    res.status(201).json(data[0]);
});

// DELETE /role_permissions/:id
app.delete("/role_permissions/:id", async (req, res) => {
    const { error } = await supabase
        .from("role_permission")
        .delete()
        .eq("RolePermissionID", req.params.id);

    if (error) return res.status(500).json({ error: error.message });

    res.json({
        status: "ok",
        message: "RolePermission deleted"
    });
});

/* ===========================================================
   GET /roles/:id/permissions  (UNIÓN REAL)
=========================================================== */
app.get("/roles/:id/permissions", async (req, res) => {
    const { data, error } = await supabase
        .from("role_permission")
        .select(`
            PermissionID,
            permission:PermissionID (
                PermissionName,
                Module,
                Action
            )
        `)
        .eq("RoleID", req.params.id);

    if (error) return res.status(500).json({ error: error.message });

    res.json(data);
});

/* ===========================================================
   START SERVER
=========================================================== */
const PORT = process.env.PORT || 10000;
app.listen(PORT, () =>
    console.log(`Backend running on port ${PORT}`)
);

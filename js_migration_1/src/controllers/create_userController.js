
// src/controllers/create_userController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all create_user" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET create_user by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new create_user", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update create_user", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE create_user", id: req.params.id });
};

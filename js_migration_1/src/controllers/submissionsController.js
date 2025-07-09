
// src/controllers/submissionsController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all submissions" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET submissions by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new submissions", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update submissions", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE submissions", id: req.params.id });
};

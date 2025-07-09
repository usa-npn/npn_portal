
// src/controllers/personController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all person" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET person by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new person", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update person", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE person", id: req.params.id });
};

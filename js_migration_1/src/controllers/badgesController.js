
// src/controllers/badgesController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all badges" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET badges by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new badges", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update badges", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE badges", id: req.params.id });
};
